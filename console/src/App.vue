<script lang="ts" setup>
import { computed, onMounted, ref, watch } from "vue";
import { useConfigMap } from "./composables/useConfigMap";
import { useRealtime, type HubRealtimeEvent } from "./composables/useRealtime";
import {
  fetchFriendInvitations,
  reconcileFriendInvitation,
  ackFriendInvitation,
} from "./composables/useFriendInvitations";
import { apiGet, apiPost } from "./api";
import type { FriendInvitationItem } from "./types";
import ConnectionSettings from "./components/ConnectionSettings.vue";
import PlanetLinksPanel from "./components/PlanetLinksPanel.vue";
import FriendInvitationManager from "./components/FriendInvitationManager.vue";
import LocalLinksPanel from "./components/LocalLinksPanel.vue";
import NewsHubPanel from "./components/NewsHubPanel.vue";
import RelationGraphPanel from "./components/RelationGraphPanel.vue";

type NavId = "maintenance" | "planetLinks" | "friendManagement" | "localLinks" | "news" | "relationGraph";
type PlanetFilter = "all" | "mutual" | "following" | "pendingBack" | "favorites";
type FriendTab = "all" | "pending" | "accepted" | "rejected" | "outbox";
type LocalLinkTab = "all" | "pending" | "approved" | "rejected";

const activeNav = ref<NavId>("planetLinks");
const planetFilter = ref<PlanetFilter>("all");
const planetSearch = ref("");
const friendTab = ref<FriendTab>("all");
const friendRefreshSignal = ref(0);
const localLinkTab = ref<LocalLinkTab>("all");
const localLinkRefreshSignal = ref(0);
const localLinksPanelRef = ref<InstanceType<typeof LocalLinksPanel> | null>(null);
const newsSearch = ref("");
const relationRefreshSignal = ref(0);
const { loading, saving, settings, fetchSettings, saveSettings } = useConfigMap();

const pendingInboxIds = ref<Set<string>>(new Set());
const pendingInboxCount = computed(() => pendingInboxIds.value.size);

const localPendingCount = ref(0);

async function refreshLocalPendingCount() {
  if (!settings.value.credentials.hasApiKey) return;
  try {
    const resp = await apiGet<{ items?: Array<{ reviewState?: number }> }>("linksPending");
    const items = Array.isArray(resp.data.items) ? resp.data.items : [];
    localPendingCount.value = items.length;
  } catch {
    localPendingCount.value = 0;
  }
}

async function refreshPendingInboxCount() {
  if (!settings.value.credentials.hasApiKey) return;
  try {
    const resp = await fetchFriendInvitations("inbox", "pending");
    const ids = resp.items.map((i) => String(i.inviteId || "").trim()).filter(Boolean);
    pendingInboxIds.value = new Set(ids);
  } catch {
    pendingInboxIds.value = new Set();
  }
}
function addPendingInbox(id: string) {
  const v = String(id || "").trim();
  if (!v || pendingInboxIds.value.has(v)) return;
  const next = new Set(pendingInboxIds.value);
  next.add(v);
  pendingInboxIds.value = next;
}
function removePendingInbox(id: string) {
  const v = String(id || "").trim();
  if (!v || !pendingInboxIds.value.has(v)) return;
  const next = new Set(pendingInboxIds.value);
  next.delete(v);
  pendingInboxIds.value = next;
}

const lastRealtimeEvent = ref<HubRealtimeEvent<unknown> | null>(null);

const autoAckedIds = new Set<string>();
const reconciledInboxIds = new Set<string>();
async function autoReconcileAckedOutbox() {
  const myId = String(settings.value.credentials.siteId || "").trim();
  if (!myId) return;
  let changed = false;
  try {
    const resp = await fetchFriendInvitations("outbox", "");
    const pending = resp.items.filter(
      (item) =>
        String(item.fromSite?.siteId || "").trim() === myId &&
        item.status === "accepted" &&
        item.deliveryStatus !== "acknowledged" &&
        String(item.inviteId || "").trim() !== "" &&
        !autoAckedIds.has(item.inviteId)
    );
    for (const item of pending) {
      autoAckedIds.add(item.inviteId);
      try {
        await reconcileFriendInvitation(item, myId);
        await ackFriendInvitation(item.inviteId, "");
        changed = true;
      } catch (error) {
        autoAckedIds.delete(item.inviteId);
        const message = error instanceof Error ? error.message : String(error);
        await ackFriendInvitation(item.inviteId, message).catch(() => undefined);
      }
    }
  } catch {
  }
  try {
    const inbox = await fetchFriendInvitations("inbox", "accepted");
    const accepted = inbox.items.filter(
      (item) =>
        item.status === "accepted" &&
        String(item.inviteId || "").trim() !== "" &&
        !reconciledInboxIds.has(item.inviteId)
    );
    for (const item of accepted) {
      reconciledInboxIds.add(item.inviteId);
      try {
        await reconcileFriendInvitation(item, myId);
        changed = true;
      } catch {
        reconciledInboxIds.delete(item.inviteId);
      }
    }
  } catch {
  }
  if (changed) {
    localLinkRefreshSignal.value += 1;
    friendRefreshSignal.value += 1;
  }
}

useRealtime(settings, (event) => {
  lastRealtimeEvent.value = event;
  if (
    event.type === "friend_invitation_reviewed" ||
    event.type === "friend_invitation_acked" ||
    event.type === "site_relation_updated"
  ) {
    void autoReconcileAckedOutbox();
  }
  if (event.type === "site_profile_updated") {
    const data = (event.data || {}) as {
      siteId?: string;
      url?: string;
      name?: string;
      description?: string;
      nodeAvatar?: string;
      avatarUrl?: string;
      rssUrl?: string;
    };
    const peerSiteId = String(data.siteId || "").trim();
    if (!peerSiteId) return;
    void apiPost("friendSyncPeer", {
      peerSiteId,
      name: String(data.name || ""),
      url: String(data.url || ""),
      description: String(data.description || ""),
      avatarUrl: String(data.nodeAvatar || data.avatarUrl || ""),
      rssUrl: String(data.rssUrl || ""),
    }).then((res) => {
      if (res.ok) {
        localLinkRefreshSignal.value += 1;
      }
    });
    return;
  }
  if (event.type === "site_relation_updated") {
    friendRefreshSignal.value += 1;
    return;
  }
  if (event.type === "friend_relation_removed") {
    const data = (event.data || {}) as {
      actorSiteId?: string;
      actorSiteUrl?: string;
      peerSiteId?: string;
      peerSiteUrl?: string;
    };
    const myId = String(settings.value.credentials.siteId || "").trim();
    const actorId = String(data.actorSiteId || "").trim();
    const peerId = String(data.peerSiteId || "").trim();
    let peerSiteId = "";
    let peerUrl = "";
    if (actorId === myId) {
      peerSiteId = peerId;
      peerUrl = String(data.peerSiteUrl || "");
    } else if (peerId === myId) {
      peerSiteId = actorId;
      peerUrl = String(data.actorSiteUrl || "");
    }
    if (peerSiteId || peerUrl) {
      void apiPost("friendDeletePeer", { peerSiteId, peerUrl }).then((res) => {
        if (res.ok) {
          localLinkRefreshSignal.value += 1;
        }
      });
    }
    friendRefreshSignal.value += 1;
    return;
  }
  const invitation = event.data as FriendInvitationItem | undefined;
  if (!invitation) return;
  const myId = String(settings.value.credentials.siteId || "").trim();
  if (!myId) return;
  const toMe = String(invitation.toSite?.siteId || "").trim() === myId;
  if (toMe) {
    const inviteId = String(invitation.inviteId || "").trim();
    const stillPending = event.type !== "friend_invitation_deleted" && invitation.status === "pending";
    if (stillPending) addPendingInbox(inviteId);
    else removePendingInbox(inviteId);
  }
  if (activeNav.value === "friendManagement") {
    friendRefreshSignal.value += 1;
  }
});

onMounted(() => {
  fetchSettings();
  refreshPendingInboxCount();
  refreshLocalPendingCount();
});

let outboxBacklogScanned = false;
watch(
  () => settings.value.credentials.siteId,
  (siteId) => {
    if (!outboxBacklogScanned && String(siteId || "").trim()) {
      outboxBacklogScanned = true;
      void autoReconcileAckedOutbox();
    }
  },
  { immediate: true }
);

watch(activeNav, () => {
  refreshLocalPendingCount();
});
</script>

<template>
  <div class="ah-page">
    <div class="ah-card">
      <div class="ah-float-nav">
        <button class="ah-float-btn" :class="{ active: activeNav === 'planetLinks' }" title="友链星球" @click="activeNav = 'planetLinks'">
          <svg viewBox="0 0 24 24" fill="none" width="16" height="16"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </button>
        <button class="ah-float-btn" :class="{ active: activeNav === 'friendManagement' }" title="友链管理" @click="activeNav = 'friendManagement'">
          <svg viewBox="0 0 24 24" fill="none" width="16" height="16"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" stroke="currentColor" stroke-width="2"/><circle cx="9" cy="7" r="4" stroke="currentColor" stroke-width="2"/></svg>
          <span v-if="pendingInboxCount > 0" class="ah-nav-badge">{{ pendingInboxCount > 99 ? '99+' : pendingInboxCount }}</span>
        </button>
        <button class="ah-float-btn" :class="{ active: activeNav === 'localLinks' }" title="本地友链" @click="activeNav = 'localLinks'">
          <svg viewBox="0 0 24 24" fill="none" width="16" height="16"><path d="M9 12h6m-6 4h6m2 5H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5.586a1 1 0 0 1 .707.293l5.414 5.414a1 1 0 0 1 .293.707V19a2 2 0 0 1-2 2z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
          <span v-if="localPendingCount > 0" class="ah-nav-badge">{{ localPendingCount > 99 ? '99+' : localPendingCount }}</span>
        </button>
        <button class="ah-float-btn" :class="{ active: activeNav === 'news' }" title="资讯" @click="activeNav = 'news'">
          <svg viewBox="0 0 1024 1024" fill="currentColor" width="16" height="16"><path d="M891.099429 580.900571c-25.490286 0-47.506286 13.897143-59.392 34.486858h-126.098286l-99.84-199.497143-76.8 281.6-51.565714-567.808-67.584-5.778286-131.035429 491.300571H64v68.900572h267.702857L426.313143 329.142857l51.273143 564.699429 67.620571 5.924571 79.579429-292.022857 38.107428 76.214857h168.813715c11.885714 20.48 33.901714 34.486857 59.392 34.486857a68.790857 68.790857 0 1 0 0-137.581714z" /></svg>
        </button>
        <button class="ah-float-btn" :class="{ active: activeNav === 'relationGraph' }" title="关系图" @click="activeNav = 'relationGraph'">
          <svg viewBox="0 0 1024 1024" fill="currentColor" width="16" height="16"><path d="M825.137 881.283a38.598 38.598 0 0 1 11.48 3.978l25.445-72.991a72.623 72.623 0 0 1-12.082-2.249l-24.843 71.262z m-680.189-219.26a38.87 38.87 0 0 1-6.149 10.487l74.684 40.425a72.539 72.539 0 0 1 5.388-10.899l-73.923-40.013z m508.99-518.16l59.891 11.9a38.63 38.63 0 0 1 4.283-11.536l-64.089-12.734c0.142 1.859 0.237 3.731 0.237 5.627 0 2.275-0.118 4.521-0.322 6.743z m-58.603 194.801v-130a73.159 73.159 0 0 1-13.971 1.353 73.44 73.44 0 0 1-10.328-0.742v129.113c3.416-0.22 6.857-0.343 10.328-0.343 4.709 0 9.366 0.217 13.971 0.619z m-267.507 344.98a73.249 73.249 0 0 1 15.864 18.423l114.44-105.334a159.056 159.056 0 0 1-13.958-20.178L327.828 683.644z m492.365 2.223l-101.239-109.99a159.002 159.002 0 0 1-14.111 20.548l100.206 108.87a73.17 73.17 0 0 1 15.144-19.428z" /><path d="M740.062 496.744c0-82.938-63.626-151.004-144.728-158.08a160.558 160.558 0 0 0-13.971-0.619c-3.471 0-6.912 0.124-10.328 0.343-82.832 5.323-148.371 74.179-148.371 158.355 0 29.099 7.839 56.364 21.509 79.811a159.056 159.056 0 0 0 13.958 20.178c29.098 35.817 73.488 58.71 123.232 58.71 49.885 0 94.386-23.024 123.479-59.017a159.002 159.002 0 0 0 14.111-20.548c13.427-23.294 21.109-50.316 21.109-79.133zM327.828 683.644c-12.628-10.493-28.853-16.808-46.556-16.808-26.463 0-49.63 14.102-62.402 35.2a72.73 72.73 0 0 0-5.388 10.899 72.673 72.673 0 0 0-5.107 26.798c0 40.26 32.637 72.897 72.897 72.897s72.897-32.637 72.897-72.897c0-13.784-3.829-26.673-10.477-37.666a73.249 73.249 0 0 0-15.864-18.423z m541.478-16.808c-18.921 0-36.156 7.211-49.113 19.031a73.202 73.202 0 0 0-15.144 19.427c-5.509 10.257-8.64 21.981-8.64 34.438 0 33.566 22.694 61.815 53.57 70.287a72.52 72.52 0 0 0 12.082 2.249c2.383 0.235 4.799 0.36 7.244 0.36 40.26 0 72.897-32.637 72.897-72.897 0-40.258-32.637-72.895-72.896-72.895zM595.335 208.664c31.421-6.101 55.627-32.373 58.603-64.801 0.204-2.222 0.322-4.468 0.322-6.743 0-1.896-0.095-3.768-0.237-5.627-2.876-37.627-34.295-67.27-72.659-67.27-40.26 0-72.897 32.637-72.897 72.897 0 36.752 27.203 67.137 62.569 72.155a73.43 73.43 0 0 0 10.328 0.742c4.78 0 9.448-0.474 13.971-1.353zM713.085 163.341c0 21.472 17.406 38.878 38.878 38.878s38.878-17.406 38.878-38.878-17.406-38.878-38.878-38.878c-14.522 0-27.176 7.968-33.851 19.765a38.62 38.62 0 0 0-4.283 11.536 39.071 39.071 0 0 0-0.744 7.577z m-565.457 484.5c0-21.472-17.407-38.878-38.878-38.878-21.472 0-38.878 17.406-38.878 38.878s17.406 38.878 38.878 38.878c12.106 0 22.918-5.534 30.049-14.21a38.845 38.845 0 0 0 6.149-10.487 38.763 38.763 0 0 0 2.68-14.181z m670.651 232.827c-21.472 0-38.878 17.406-38.878 38.878s17.406 38.878 38.878 38.878 38.878-17.406 38.878-38.878c0-14.84-8.317-27.733-20.541-34.285a38.626 38.626 0 0 0-11.48-3.978 39.008 39.008 0 0 0-6.857-0.615z" /></svg>
        </button>
        <button class="ah-float-btn" :class="{ active: activeNav === 'maintenance' }" title="接入配置" @click="activeNav = 'maintenance'">
          <svg viewBox="0 0 24 24" fill="none" width="16" height="16"><path d="M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6z" stroke="currentColor" stroke-width="2"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68 1.65 1.65 0 0 0 10 3.17V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z" stroke="currentColor" stroke-width="2"/></svg>
        </button>
      </div>

      <div class="ah-corner ah-corner-tl"></div>
      <div class="ah-corner ah-corner-tr"></div>
      <div class="ah-corner ah-corner-bl"></div>
      <div class="ah-corner ah-corner-br"></div>

      <div class="ah-topbar">
        <div class="ah-topbar-left">
          <svg viewBox="0 0 1024 1024" width="26" height="26" aria-hidden="true">
            <path d="M970.474667 521.813333a14.08 14.08 0 0 0-10.24 0l-49.92 29.013334a13.653333 13.653333 0 0 0-5.12 18.346666 13.653333 13.653333 0 0 0 18.346666 4.693334l49.92-29.013334a13.226667 13.226667 0 0 0 5.12-17.92 13.226667 13.226667 0 0 0-8.106666-5.12zM754.581333 218.026667l23.466667-13.653334a29.013333 29.013333 0 0 0 10.666667-39.68 28.586667 28.586667 0 0 0-39.68-10.666666L576.234667 256a28.586667 28.586667 0 0 1-15.786667 0 29.013333 29.013333 0 0 1-17.92-13.653333 29.866667 29.866667 0 0 1 12.373333-40.96l85.333334-50.346667a390.826667 390.826667 0 0 0-526.933334 368.213333 394.24 394.24 0 0 0 37.973334 170.666667l-56.32 32.853333a20.053333 20.053333 0 0 0-7.253334 27.306667 19.626667 19.626667 0 0 0 12.373334 9.386667 20.053333 20.053333 0 0 0 14.933333-2.133334L243.434667 682.666667a32 32 0 0 1 34.56 16.213333 33.28 33.28 0 0 1-3.413334 38.826667L71.488 853.333333a29.44 29.44 0 0 0-10.666667 39.68 30.72 30.72 0 0 0 17.92 13.653334 29.866667 29.866667 0 0 0 21.76-2.986667L388.501333 738.133333a14.506667 14.506667 0 0 1 8.96 6.826667 15.36 15.36 0 0 1-5.546666 20.48l-119.466667 69.546667A389.12 389.12 0 0 0 834.794667 725.333333l-39.68 23.04a15.36 15.36 0 0 1-11.52 0 14.506667 14.506667 0 0 1-8.96-6.826666 14.933333 14.933333 0 0 1 5.546666-20.48l81.066667-47.36a395.093333 395.093333 0 0 0-106.666667-459.093334zM1016.981333 494.933333a12.373333 12.373333 0 0 0-13.226666 0 11.946667 11.946667 0 0 0-6.4 11.52 12.373333 12.373333 0 0 0 6.4 11.52 12.373333 12.373333 0 0 0 13.226666 0 13.226667 13.226667 0 0 0 6.4-11.52 12.373333 12.373333 0 0 0-6.4-11.52zM81.301333 357.12L85.568 354.133333a14.08 14.08 0 0 0 0-5.12v-2.986666A14.08 14.08 0 0 0 85.568 341.333333l-4.266667-3.413333h-27.733333v-23.466667a14.08 14.08 0 0 0 0-5.12l-2.986667-2.986666L42.901333 305.493333H37.781333a6.826667 6.826667 0 0 0-2.986666 2.986667 8.533333 8.533333 0 0 0 0 5.12v23.466667h-29.866667L0.234667 341.333333a14.08 14.08 0 0 0 0 5.12v2.986667a14.08 14.08 0 0 0 0 4.693333l2.986666 2.986667a8.533333 8.533333 0 0 0 5.12 0h24.32v22.613333a8.533333 8.533333 0 0 0 0.853334 4.266667 6.826667 6.826667 0 0 0 2.986666 2.986667H42.901333a14.08 14.08 0 0 0 5.12 0A9.813333 9.813333 0 0 0 52.714667 384a14.08 14.08 0 0 0 0-5.12v-20.906667h22.613333a8.533333 8.533333 0 0 0 5.973333-0.853333zM810.901333 165.12a27.733333 27.733333 0 0 0 28.16 0 28.16 28.16 0 1 0-28.16 0zM876.608 853.333333h-16.64v-12.8a6.826667 6.826667 0 0 0 0-3.84 3.84 3.84 0 0 0-2.56-2.56 6.826667 6.826667 0 0 0-3.84 0 5.12 5.12 0 0 0-3.84 0 5.546667 5.546667 0 0 0-2.986667 2.56 12.373333 12.373333 0 0 0 0 3.84v12.8h-16.64l-2.986666 2.986667a10.24 10.24 0 0 0 0 3.84 12.373333 12.373333 0 0 0 0 3.84l2.986666 2.986667h16.64v12.8a12.373333 12.373333 0 0 0 0 3.84 5.546667 5.546667 0 0 0 2.986667 2.56 5.12 5.12 0 0 0 3.84 0 6.826667 6.826667 0 0 0 3.84 0 3.84 3.84 0 0 0 2.56-2.56 6.826667 6.826667 0 0 0 0-3.84v-12.8h16.64s2.133333 0 2.56-2.986667a6.826667 6.826667 0 0 0 0-3.84 5.12 5.12 0 0 0 0-3.84s-1.706667-2.986667-2.56-2.986667z" fill="#8081FF" />
          </svg>
          <span class="ah-topbar-brand">ASTRA<span class="ah-topbar-accent">HUB</span></span>
          <span class="ah-topbar-page-title" v-html="activeNav === 'maintenance' ? '星链<span class=ah-kw>接入配置</span>' : activeNav === 'planetLinks' ? '<span class=ah-kw>友链</span>星球' : activeNav === 'friendManagement' ? '<span class=ah-kw>友链</span>管理' : activeNav === 'localLinks' ? '本地<span class=ah-kw>友链</span>' : activeNav === 'relationGraph' ? '<span class=ah-kw>关系</span>图' : '星链<span class=ah-kw>资讯</span>'"></span>
        </div>
        <div class="ah-topbar-right">
          <template v-if="activeNav === 'planetLinks'">
            <button
              v-for="t in [{id:'all',label:'全部'},{id:'favorites',label:'已收藏'},{id:'pendingBack',label:'未关注'},{id:'following',label:'已关注'},{id:'mutual',label:'互相关注'}]"
              :key="t.id"
              class="ah-topbar-tab"
              :class="{ active: planetFilter === t.id }"
              @click="planetFilter = t.id as PlanetFilter"
            >{{ t.label }}</button>
            <div class="ah-topbar-search">
              <svg viewBox="0 0 24 24" fill="none" class="ah-topbar-search-icon" aria-hidden="true">
                <circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="2" />
                <path d="M20 20l-3.5-3.5" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
              </svg>
              <input v-model="planetSearch" type="search" class="ah-topbar-search-input" placeholder="搜索友链" />
            </div>
          </template>
          <template v-if="activeNav === 'friendManagement'">
            <button
              v-for="t in [{id:'all',label:'全部'},{id:'pending',label:'待审核'},{id:'accepted',label:'已通过'},{id:'rejected',label:'已拒绝'},{id:'outbox',label:'发出的'}]"
              :key="t.id"
              class="ah-topbar-tab ah-topbar-tab--rel"
              :class="{ active: friendTab === t.id }"
              @click="friendTab = t.id as FriendTab"
            >
              {{ t.label }}
              <span v-if="t.id === 'pending' && pendingInboxCount > 0" class="ah-tab-badge">{{ pendingInboxCount > 99 ? '99+' : pendingInboxCount }}</span>
            </button>
          </template>
          <template v-if="activeNav === 'localLinks'">
            <button
              v-for="t in [{id:'all',label:'全部'},{id:'pending',label:'待审核'},{id:'approved',label:'已通过'},{id:'rejected',label:'已驳回'}]"
              :key="t.id"
              class="ah-topbar-tab ah-topbar-tab--rel"
              :class="{ active: localLinkTab === t.id }"
              @click="localLinkTab = t.id as LocalLinkTab"
            >
              {{ t.label }}
              <span v-if="t.id === 'pending' && localPendingCount > 0" class="ah-tab-badge">{{ localPendingCount > 99 ? '99+' : localPendingCount }}</span>
            </button>
            <button class="ah-topbar-tab" @click="localLinksPanelRef?.openGroupManager()">分组管理</button>
            <button class="ah-topbar-tab" @click="localLinksPanelRef?.openCreate()">添加友链</button>
          </template>
          <template v-if="activeNav === 'news'">
            <div class="ah-topbar-search">
              <svg viewBox="0 0 24 24" fill="none" class="ah-topbar-search-icon" aria-hidden="true">
                <circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="2" />
                <path d="M20 20l-3.5-3.5" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
              </svg>
              <input v-model="newsSearch" type="search" class="ah-topbar-search-input" placeholder="搜索资讯" />
            </div>
          </template>
          <template v-if="activeNav === 'relationGraph'">
            <button class="ah-topbar-tab" @click="relationRefreshSignal++">刷新</button>
          </template>
          <button
            v-if="activeNav === 'maintenance'"
            class="ah-topbar-tab"
            :class="{ active: true }"
            :disabled="saving"
            @click="saveSettings"
          >
            {{ saving ? '保存中...' : '保存设置' }}
          </button>
        </div>
      </div>

      <div class="ah-body">
        <div class="ah-content">
          <div v-if="loading" class="ah-loading">
            <div class="uv-loader"><span class="uv-loader-text">loading</span><span class="uv-load"></span></div>
          </div>
          <ConnectionSettings
            v-if="!loading && activeNav === 'maintenance'"
            :settings="settings"
            :saving="saving"
            :persist-settings="saveSettings"
          />
          <PlanetLinksPanel
            v-else-if="!loading && activeNav === 'planetLinks'"
            :settings="settings"
            :active-filter="planetFilter"
            :search-query="planetSearch"
            :persist-settings="saveSettings"
            :realtime-event="lastRealtimeEvent"
          />
          <FriendInvitationManager
            v-else-if="!loading && activeNav === 'friendManagement'"
            :settings="settings"
            :active-tab="friendTab"
            :refresh-signal="friendRefreshSignal"
            @pending-inbox-remove="removePendingInbox"
          />
          <LocalLinksPanel
            v-else-if="!loading && activeNav === 'localLinks'"
            ref="localLinksPanelRef"
            :settings="settings"
            :active-tab="localLinkTab"
            :refresh-signal="localLinkRefreshSignal"
          />
          <NewsHubPanel v-else-if="!loading && activeNav === 'news'" :settings="settings" :search-query="newsSearch" :persist-settings="saveSettings" />
          <RelationGraphPanel v-else-if="!loading && activeNav === 'relationGraph'" :settings="settings" :refresh-signal="relationRefreshSignal" />
          <div v-else-if="!loading" class="ah-placeholder">
            该模块将在后续阶段开放。
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style>
.ah-page{padding:16px;flex:1;display:flex;flex-direction:column;min-height:0;overflow:hidden;height:calc(100vh - 64px);max-height:calc(100vh - 64px);box-sizing:border-box;font-family:"Comic Sans MS","Yuanti SC","圆体-简","华文圆体","HYWenHei-85W","LXGW WenKai","Microsoft YaHei UI","PingFang SC","Hiragino Sans GB","Microsoft YaHei",system-ui,sans-serif;letter-spacing:.02em}
.ah-card{position:relative;flex:1;min-height:0;display:flex;flex-direction:column;padding:16px;padding-left:72px;background:rgba(255,255,255,.75);backdrop-filter:blur(24px);border:1px solid rgba(0,0,0,.08);border-radius:24px;box-shadow:0 18px 48px rgba(2,6,23,.06);overflow:hidden}
.ah-corner{position:absolute;width:24px;height:24px;pointer-events:none;z-index:5}
.ah-corner-tl{top:8px;left:8px;border-top:2px solid rgba(37,99,235,.2);border-left:2px solid rgba(37,99,235,.2);border-top-left-radius:24px}
.ah-corner-tr{top:8px;right:8px;border-top:2px solid rgba(37,99,235,.2);border-right:2px solid rgba(37,99,235,.2);border-top-right-radius:24px}
.ah-corner-bl{bottom:8px;left:8px;border-bottom:2px solid rgba(37,99,235,.2);border-left:2px solid rgba(37,99,235,.2);border-bottom-left-radius:24px}
.ah-corner-br{bottom:8px;right:8px;border-bottom:2px solid rgba(37,99,235,.2);border-right:2px solid rgba(37,99,235,.2);border-bottom-right-radius:24px}
.ah-topbar{display:flex;align-items:center;justify-content:space-between;height:52px;padding:0 20px;margin-left:-56px;margin-bottom:14px;background:rgba(255,255,255,.7);backdrop-filter:blur(16px);border:1px solid rgba(0,0,0,.06);border-radius:999px;box-shadow:0 4px 16px rgba(0,0,0,.03);flex-shrink:0;position:relative;z-index:2}
.ah-topbar-left{display:flex;align-items:center;gap:12px}
.ah-topbar-brand{font-size:15px;font-weight:800;letter-spacing:-.5px;color:#1e293b}
.ah-topbar-accent{color:#7c3aed}
.ah-topbar-page-title{font-size:13px;font-weight:600;color:#64748b;font-style:italic;letter-spacing:-.2px}
.ah-topbar-page-title :deep(.ah-kw){color:#7c3aed;font-style:italic}
.ah-topbar-right{display:flex;align-items:center;gap:10px}
.ah-topbar-tab{display:inline-flex;align-items:center;box-sizing:border-box;height:30px;outline:none;padding:0 14px;border:2px dashed #64748b;border-radius:15px;background-color:#f1f5f9;color:#64748b;font-size:11px;font-weight:600;cursor:pointer;transition:transform .2s ease-out;box-shadow:0 0 0 3px #f1f5f9,1.5px 1.5px 3px 1px rgba(0,0,0,.15)}
.ah-topbar-tab:hover{transform:translateY(-4px) translateX(-2px);box-shadow:0 0 0 3px #f1f5f9,2px 5px 0 0 currentColor}
.ah-topbar-tab:active{transform:translateY(1px) translateX(1px);box-shadow:0 0 0 3px #f1f5f9,0 0 0 0 currentColor}
.ah-topbar-tab.active{border-color:#075985;color:#075985;background-color:#f0f9ff;box-shadow:0 0 0 3px #f0f9ff,1.5px 1.5px 3px 1px rgba(0,0,0,.15)}
.ah-topbar-tab.active:hover{box-shadow:0 0 0 3px #f0f9ff,2px 5px 0 0 #075985}
.ah-topbar-search{display:inline-flex;align-items:center;box-sizing:border-box;gap:6px;padding:0 12px;height:30px;border:2px dashed #64748b;border-radius:999px;background:#f1f5f9;box-shadow:0 0 0 3px #f1f5f9,1.5px 1.5px 3px 1px rgba(0,0,0,.15);transition:transform .2s ease-out}
.ah-topbar-search:focus-within{transform:translateY(-2px) translateX(-1px);border-color:#075985;background:#f0f9ff;box-shadow:0 0 0 3px #f0f9ff,2px 5px 0 0 #075985}
.ah-topbar-search-icon{width:14px;height:14px;color:#64748b;flex-shrink:0}
.ah-topbar-search:focus-within .ah-topbar-search-icon{color:#075985}
.ah-topbar-search-input{box-sizing:border-box;width:140px;height:20px;border:none;outline:none;background:transparent;color:#0f172a;font-size:12px;font-weight:600;line-height:20px;padding:0;margin:0}
.ah-topbar-search-input::placeholder{color:#94a3b8;font-weight:500}
.ah-topbar-search-input::-webkit-search-cancel-button{-webkit-appearance:none;height:12px;width:12px;cursor:pointer;background:linear-gradient(45deg,transparent 45%,#94a3b8 45% 55%,transparent 55%),linear-gradient(-45deg,transparent 45%,#94a3b8 45% 55%,transparent 55%)}
.ah-body{position:relative;flex:1;min-height:0;display:flex;background:#fff;border:1px solid rgba(0,0,0,.06);border-radius:28px;box-shadow:0 4px 16px rgba(0,0,0,.03);overflow:hidden}
.ah-float-nav{position:absolute;left:16px;top:50%;transform:translateY(-50%);display:flex;flex-direction:column;gap:6px;padding:10px 8px;background:rgba(248,250,252,.95);border:1px solid rgba(0,0,0,.05);border-radius:18px;box-shadow:0 4px 16px rgba(0,0,0,.04);z-index:20}
.ah-float-btn{position:relative;width:36px;height:36px;border-radius:12px;border:none;background:transparent;color:#94a3b8;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:all .18s}
.ah-float-btn:hover{background:rgba(37,99,235,.06);color:#2563eb}
.ah-float-btn.active{background:rgba(37,99,235,.1);color:#2563eb;box-shadow:0 0 12px rgba(37,99,235,.1)}
.ah-nav-badge{position:absolute;top:2px;right:2px;min-width:16px;height:16px;padding:0 4px;box-sizing:border-box;display:inline-flex;align-items:center;justify-content:center;border-radius:999px;background:#ef4444;color:#fff;font-size:10px;font-weight:700;line-height:1;box-shadow:0 0 0 2px #fff;pointer-events:none}
.ah-topbar-tab--rel{position:relative;gap:6px}
.ah-tab-badge{min-width:16px;height:16px;padding:0 5px;box-sizing:border-box;display:inline-flex;align-items:center;justify-content:center;border-radius:999px;background:#ef4444;color:#fff;font-size:10px;font-weight:700;line-height:1}
.ah-content{flex:1;min-height:0;display:flex;flex-direction:column;overflow:hidden;position:relative}
.ah-content *::-webkit-scrollbar{display:none}
.ah-content *{scrollbar-width:none}
.ah-loading{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;background:rgba(255,255,255,.8);z-index:10}
.ah-loading .uv-loader{width:80px;height:50px;position:relative}
.ah-loading .uv-loader-text{position:absolute;top:0;color:#C8B6FF;animation:uvtext 3.5s ease both infinite;font-size:.8rem;letter-spacing:1px}
.ah-loading .uv-load{background-color:#9A79FF;border-radius:50px;display:block;height:16px;width:16px;bottom:0;position:absolute;transform:translateX(64px);animation:uvloading 3.5s ease both infinite}
.ah-loading .uv-load::before{position:absolute;content:"";width:100%;height:100%;background-color:#D1C2FF;border-radius:inherit;animation:uvloading2 3.5s ease both infinite}
@keyframes uvtext{0%{letter-spacing:1px;transform:translateX(0)}40%{letter-spacing:2px;transform:translateX(26px)}80%{letter-spacing:1px;transform:translateX(32px)}90%{letter-spacing:2px;transform:translateX(0)}100%{letter-spacing:1px;transform:translateX(0)}}
@keyframes uvloading{0%{width:16px;transform:translateX(0)}40%{width:100%;transform:translateX(0)}80%{width:16px;transform:translateX(64px)}90%{width:100%;transform:translateX(0)}100%{width:16px;transform:translateX(0)}}
@keyframes uvloading2{0%{transform:translateX(0);width:16px}40%{transform:translateX(0);width:80%}80%{width:100%;transform:translateX(0)}90%{width:80%;transform:translateX(15px)}100%{transform:translateX(0);width:16px}}
.ah-placeholder{padding:48px;text-align:center;color:#94a3b8;font-size:13px}
</style>
