<script lang="ts" setup>
import { computed, onMounted, onBeforeUnmount, ref, watch } from "vue";
import { Toast } from "../toast";
import EmptyState from "./common/EmptyState.vue";
import { useLocalLinks } from "../composables/useLocalLinks";
import { useLinkGroups, type LinkGroupItem } from "../composables/useLinkGroups";
import { HERO_MASCOT_DATA_URI } from "../data/heroMascot";
import type { AstraHubSettings, LocalLinkItem } from "../types";

const props = defineProps<{
  settings: AstraHubSettings;
  activeTab?: "all" | "pending" | "approved" | "rejected";
  refreshSignal?: number;
}>();

const { loading, items, error, fetchAll, saveLink, deleteLink, approveLink, rejectLink } = useLocalLinks();

const {
  items: groups,
  fetchAll: fetchGroups,
  saveGroup,
  deleteGroup: removeGroup,
} = useLinkGroups();

function groupNameOf(gid: number): string {
  if (!gid) return "";
  return groups.value.find((g) => g.gid === gid)?.displayName || "";
}

const tab = computed(() => props.activeTab || "all");

const DEFAULT_AVATAR = `data:image/svg+xml,${encodeURIComponent(
  '<svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 48 48"><rect width="48" height="48" rx="24" fill="#e2e8f0"/><path d="M24 26a6 6 0 100-12 6 6 0 000 12zm0 3c-6 0-12 3-12 8v1h24v-1c0-5-6-8-12-8z" fill="#94a3b8"/></svg>'
)}`;

const filteredItems = computed(() => {
  const list = items.value;
  switch (tab.value) {
    case "pending":
      return list.filter((i) => i.reviewState === 2);
    case "approved":
      return list.filter((i) => i.reviewState === 1);
    case "rejected":
      return list.filter((i) => i.reviewState === 0);
    default:
      return list;
  }
});

const pendingCount = computed(() => items.value.filter((i) => i.reviewState === 2).length);

const editDialogVisible = ref(false);
const editForm = ref<Partial<LocalLinkItem>>({});
const saving = ref(false);

function openCreate() {
  editForm.value = {
    flid: 0,
    siteName: "",
    siteUrl: "",
    avatarUrl: "",
    summary: "",
    rssUrl: "",
    applicantEmail: "",
    groupId: 0,
    reviewState: 1,
  };
  editDialogVisible.value = true;
}

function openEdit(item: LocalLinkItem) {
  editForm.value = { ...item };
  editDialogVisible.value = true;
}

function closeEdit() {
  if (saving.value) return;
  editDialogVisible.value = false;
  editForm.value = {};
  groupSelectOpen.value = false;
}

const groupSelectOpen = ref(false);
function toggleGroupSelect() {
  groupSelectOpen.value = !groupSelectOpen.value;
}
function selectEditGroup(gid: number) {
  editForm.value.groupId = gid;
  groupSelectOpen.value = false;
}
const selectedGroupLabel = computed(() => {
  const gid = Number(editForm.value.groupId || 0);
  if (!gid) return "未分组";
  return groups.value.find((g) => g.gid === gid)?.displayName || "未分组";
});

async function submitEdit() {
  const f = editForm.value;
  if (!String(f.siteName || "").trim() || !String(f.siteUrl || "").trim()) {
    Toast.warning("站点名称和链接为必填");
    return;
  }
  saving.value = true;
  try {
    const res = await saveLink(f);
    if (res.ok) {
      Toast.success("已保存");
      editDialogVisible.value = false;
      editForm.value = {};
    } else {
      Toast.error(res.message || "保存失败");
    }
  } finally {
    saving.value = false;
  }
}

const deleteDialogVisible = ref(false);
const deleteTarget = ref<LocalLinkItem | null>(null);
const deleting = ref(false);

function openDelete(item: LocalLinkItem) {
  deleteTarget.value = item;
  deleteDialogVisible.value = true;
}

function closeDelete() {
  if (deleting.value) return;
  deleteDialogVisible.value = false;
  deleteTarget.value = null;
}

async function submitDelete() {
  if (!deleteTarget.value) return;
  deleting.value = true;
  try {
    const ok = await deleteLink(deleteTarget.value.flid);
    if (ok) {
      Toast.success("已删除");
      deleteDialogVisible.value = false;
      deleteTarget.value = null;
    } else {
      Toast.error("删除失败");
    }
  } finally {
    deleting.value = false;
  }
}

const approving = ref<number[]>([]);
async function doApprove(item: LocalLinkItem) {
  if (approving.value.includes(item.flid)) return;
  approving.value = [...approving.value, item.flid];
  try {
    const ok = await approveLink(item.flid);
    if (ok) {
      Toast.success("已通过，已通知申请者");
    } else {
      Toast.error("操作失败");
    }
  } finally {
    approving.value = approving.value.filter((id) => id !== item.flid);
  }
}

const rejectDialogVisible = ref(false);
const rejectTarget = ref<LocalLinkItem | null>(null);
const rejectReason = ref("");
const rejecting = ref(false);

function openReject(item: LocalLinkItem) {
  rejectTarget.value = item;
  rejectReason.value = "";
  rejectDialogVisible.value = true;
}

function closeReject() {
  if (rejecting.value) return;
  rejectDialogVisible.value = false;
  rejectTarget.value = null;
  rejectReason.value = "";
}

async function submitReject() {
  if (!rejectTarget.value) return;
  rejecting.value = true;
  try {
    const ok = await rejectLink(rejectTarget.value.flid, rejectReason.value.trim());
    if (ok) {
      Toast.success("已驳回，已通知申请者");
      rejectDialogVisible.value = false;
      rejectTarget.value = null;
      rejectReason.value = "";
    } else {
      Toast.error("操作失败");
    }
  } finally {
    rejecting.value = false;
  }
}

const groupDialogVisible = ref(false);
const groupForm = ref<{ gid: number; displayName: string }>({ gid: 0, displayName: "" });
const groupSaving = ref(false);

function openGroupManager() {
  groupForm.value = { gid: 0, displayName: "" };
  groupDialogVisible.value = true;
}

function closeGroupManager() {
  if (groupSaving.value) return;
  groupDialogVisible.value = false;
  groupForm.value = { gid: 0, displayName: "" };
}

function editGroup(g: LinkGroupItem) {
  groupForm.value = { gid: g.gid, displayName: g.displayName };
}

async function submitGroup() {
  const name = groupForm.value.displayName.trim();
  if (!name) {
    Toast.warning("请填写分组名称");
    return;
  }
  groupSaving.value = true;
  try {
    const res = await saveGroup({
      gid: groupForm.value.gid || undefined,
      displayName: name,
    });
    if (res.ok) {
      Toast.success("已保存分组");
      groupForm.value = { gid: 0, displayName: "" };
    } else {
      Toast.error(res.message || "保存分组失败（可能重名）");
    }
  } finally {
    groupSaving.value = false;
  }
}

async function deleteGroupConfirm(g: LinkGroupItem) {
  const ok = await removeGroup(g.gid);
  if (ok) {
    Toast.success("已删除分组，相关友链已解绑");
    await fetchAll();
  } else {
    Toast.error("删除分组失败");
  }
}

function stateLabel(state: number) {
  switch (state) {
    case 1:
      return "已通过";
    case 2:
      return "待审核";
    default:
      return "已驳回";
  }
}

function statusClass(state: number) {
  switch (state) {
    case 1:
      return "ok";
    case 2:
      return "pending";
    default:
      return "warn";
  }
}

function sourceLabel(type: string) {
  if (type === "visitor") return "访客申请";
  if (type === "invitation") return "在线邀请";
  return "后台添加";
}

function descPreview(summary?: string) {
  const text = String(summary || "").trim();
  if (!text) return "暂无描述";
  return text.length > 15 ? `${text.slice(0, 15)}…` : text;
}

function formatTime(ts: number) {
  if (!ts) return "-";
  const d = new Date(ts * 1000);
  return d.toLocaleString("zh-CN", {
    year: "numeric",
    month: "2-digit",
    day: "2-digit",
    hour: "2-digit",
    minute: "2-digit",
  });
}

const isScrolling = ref(false);
let scrollEndTimer: ReturnType<typeof setTimeout> | null = null;

function onScroll() {
  isScrolling.value = true;
  if (scrollEndTimer) {
    clearTimeout(scrollEndTimer);
  }
  scrollEndTimer = setTimeout(() => {
    isScrolling.value = false;
    scrollEndTimer = null;
  }, 150);
}

function onDocClick(e: MouseEvent) {
  const target = e.target as HTMLElement | null;
  if (target && !target.closest(".ll-select")) {
    groupSelectOpen.value = false;
  }
}

onMounted(() => {
  void fetchAll();
  void fetchGroups();
  document.addEventListener("click", onDocClick);
});

onBeforeUnmount(() => {
  document.removeEventListener("click", onDocClick);
  if (scrollEndTimer) {
    clearTimeout(scrollEndTimer);
    scrollEndTimer = null;
  }
});

watch(
  () => props.refreshSignal,
  () => {
    void fetchAll({ silent: true });
  }
);

defineExpose({ pendingCount, openCreate, openGroupManager });
</script>

<template>
  <div class="ll-wrap">
    <div class="ll-table-wrap" :class="{ 'is-scrolling': isScrolling }" @scroll="onScroll">
      <div v-if="loading" class="ll-loading">
        <div class="uv-loader"><span class="uv-loader-text">loading</span><span class="uv-load"></span></div>
      </div>

      <div v-else-if="error" class="ll-empty">
        <EmptyState :text="error" hint="请检查网络或稍后重试" />
      </div>

      <div v-else-if="!filteredItems.length" class="ll-empty">
        <EmptyState
          :text="tab === 'pending' ? '暂无待审核申请' : '暂无友链'"
          :hint="tab === 'pending' ? '访客通过前台申请的友链会出现在这里' : '点击右上角添加友链，或在前台开放访客申请'"
        />
      </div>

      <div v-else class="ll-table">
        <div class="ll-row ll-row--head">
          <div class="avatar-cell">头像</div>
          <div class="name-cell">站点</div>
          <div class="desc-cell">描述</div>
          <div class="rss-cell">RSS</div>
          <div class="group-cell">分组</div>
          <div class="status-cell">状态</div>
          <div class="source-cell">来源</div>
          <div class="time-cell">时间</div>
          <div class="action-cell">操作</div>
        </div>
        <div
          v-for="item in filteredItems"
          :key="item.flid"
          class="ll-row"
          :class="`ll-row--${statusClass(item.reviewState)}`"
        >
          <div class="avatar-cell">
            <img
              class="site-avatar"
              :src="item.avatarUrl || DEFAULT_AVATAR"
              alt=""
              @error="($event.target as HTMLImageElement).src = DEFAULT_AVATAR"
            />
          </div>

          <div class="name-cell">
            <div class="site-name">{{ item.siteName || "-" }}</div>
            <a
              v-if="item.siteUrl"
              class="site-url external-link"
              :href="item.siteUrl"
              target="_blank"
              rel="noopener nofollow"
              :title="item.siteUrl"
            >{{ item.siteUrl }}</a>
            <div v-else class="site-url">-</div>
          </div>

          <div class="desc-cell">
            <span class="desc-text" :title="item.summary || '暂无描述'">{{ descPreview(item.summary) }}</span>
          </div>

          <div class="rss-cell">
            <a
              v-if="item.rssUrl"
              class="contact-rss external-link"
              :href="item.rssUrl"
              target="_blank"
              rel="noopener nofollow"
              :title="item.rssUrl"
            >{{ item.rssUrl }}</a>
            <div v-else class="contact-rss">-</div>
          </div>

          <div class="group-cell">
            <span v-if="item.groupId && groupNameOf(item.groupId)" class="ll-group-tag">{{ groupNameOf(item.groupId) }}</span>
            <span v-else class="muted">未分组</span>
          </div>

          <div class="status-cell">
            <span class="status-pill" :class="statusClass(item.reviewState)">{{ stateLabel(item.reviewState) }}</span>
          </div>

          <div class="source-cell">
            <span class="source-text">{{ sourceLabel(item.sourceType) }}</span>
          </div>

          <div class="time-cell">
            <div>{{ formatTime(item.createdAt) }}</div>
          </div>

          <div class="action-cell">
            <template v-if="item.reviewState === 2">
              <button class="action-btn approve" :disabled="approving.includes(item.flid)" @click="doApprove(item)">
                {{ approving.includes(item.flid) ? "处理中" : "通过" }}
              </button>
              <button class="action-btn reject" @click="openReject(item)">驳回</button>
            </template>
            <template v-else>
              <button class="action-btn" @click="openEdit(item)">编辑</button>
            </template>
            <button class="action-btn delete" @click="openDelete(item)">删除</button>
          </div>
        </div>
      </div>
    </div>

    <div v-if="editDialogVisible" class="ll-modal-overlay" @click.self="closeEdit">
      <div class="ll-modal ll-modal-lg ll-modal-mascot">
        <div class="ll-mascot-side">
          <img class="ll-mascot-img" :src="HERO_MASCOT_DATA_URI" alt="" draggable="false" />
          <div class="ll-mascot-bubble">{{ editForm.flid ? "改好资料就点保存，我帮你同步到星球～" : "填上对方的站点名和链接就行，其它能填则填，分好组更整齐哦！" }}</div>
        </div>
        <div class="ll-modal-main">
        <div class="ll-modal-header">
          <h3 class="ll-modal-title">{{ editForm.flid ? "编辑友链" : "添加友链" }}</h3>
          <button class="ll-modal-close" @click="closeEdit">
            <svg width="20" height="20" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 5l10 10M15 5l-10 10" /></svg>
          </button>
        </div>
        <div class="ll-modal-body">
          <div class="ll-grid">
            <div class="ll-field">
              <label class="ll-label">站点名称<span class="ll-req">*</span></label>
              <input v-model="editForm.siteName" type="text" class="ll-input" placeholder="站点名称" />
            </div>
            <div class="ll-field">
              <label class="ll-label">站点链接<span class="ll-req">*</span></label>
              <input v-model="editForm.siteUrl" type="url" class="ll-input" placeholder="https://example.com" />
            </div>
            <div class="ll-field">
              <label class="ll-label">头像链接</label>
              <input v-model="editForm.avatarUrl" type="url" class="ll-input" placeholder="https://example.com/avatar.png" />
            </div>
            <div class="ll-field">
              <label class="ll-label">RSS 链接</label>
              <input v-model="editForm.rssUrl" type="url" class="ll-input" placeholder="https://example.com/rss" />
            </div>
            <div class="ll-field">
              <label class="ll-label">联系邮箱</label>
              <input v-model="editForm.applicantEmail" type="email" class="ll-input" placeholder="contact@example.com" />
            </div>
            <div class="ll-field">
              <label class="ll-label">所属分组</label>
              <div class="ll-select" :class="{ open: groupSelectOpen }">
                <button type="button" class="ll-select-trigger" @click.stop="toggleGroupSelect">
                  <span>{{ selectedGroupLabel }}</span>
                  <svg viewBox="0 0 20 20" fill="none" class="ll-select-arrow" :class="{ open: groupSelectOpen }">
                    <path d="M5 7.5L10 12.5L15 7.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
                  </svg>
                </button>
                <div v-if="groupSelectOpen" class="ll-select-menu">
                  <button type="button" class="ll-select-option" :class="{ active: !editForm.groupId }" @click.stop="selectEditGroup(0)">未分组</button>
                  <button
                    v-for="g in groups"
                    :key="g.gid"
                    type="button"
                    class="ll-select-option"
                    :class="{ active: editForm.groupId === g.gid }"
                    @click.stop="selectEditGroup(g.gid)"
                  >{{ g.displayName }}</button>
                </div>
              </div>
            </div>
          </div>
          <div class="ll-field">
            <label class="ll-label">站点描述</label>
            <textarea v-model="editForm.summary" class="ll-input ll-textarea" placeholder="简单介绍"></textarea>
          </div>
        </div>
        <div class="ll-modal-footer">
          <button class="ll-btn ll-btn-ghost" @click="closeEdit">取消</button>
          <button class="ll-btn ll-btn-primary" :disabled="saving" @click="submitEdit">{{ saving ? "保存中" : "保存" }}</button>
        </div>
        </div>
      </div>
    </div>

    <div v-if="rejectDialogVisible" class="ll-modal-overlay" @click.self="closeReject">
      <div class="ll-modal ll-modal-sm">
        <div class="ll-modal-header">
          <h3 class="ll-modal-title">驳回友链</h3>
          <button class="ll-modal-close" @click="closeReject">
            <svg width="20" height="20" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 5l10 10M15 5l-10 10" /></svg>
          </button>
        </div>
        <div class="ll-modal-body">
          <p class="ll-confirm-text">将驳回「{{ rejectTarget?.siteName }}」的友链申请，并通过邮件通知申请者。</p>
          <div class="ll-field">
            <label class="ll-label">驳回原因</label>
            <textarea v-model="rejectReason" class="ll-input ll-textarea" placeholder="不符合本站友链收录规则"></textarea>
          </div>
        </div>
        <div class="ll-modal-footer">
          <button class="ll-btn ll-btn-ghost" @click="closeReject">取消</button>
          <button class="ll-btn ll-btn-reject" :disabled="rejecting" @click="submitReject">{{ rejecting ? "处理中" : "确认驳回" }}</button>
        </div>
      </div>
    </div>

    <div v-if="deleteDialogVisible" class="ll-modal-overlay" @click.self="closeDelete">
      <div class="ll-modal ll-modal-sm">
        <div class="ll-modal-header">
          <h3 class="ll-modal-title">删除友链</h3>
          <button class="ll-modal-close" @click="closeDelete">
            <svg width="20" height="20" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 5l10 10M15 5l-10 10" /></svg>
          </button>
        </div>
        <div class="ll-modal-body">
          <p class="ll-confirm-text">确定删除「{{ deleteTarget?.siteName }}」吗？此操作不可恢复。</p>
        </div>
        <div class="ll-modal-footer">
          <button class="ll-btn ll-btn-ghost" @click="closeDelete">取消</button>
          <button class="ll-btn ll-btn-danger" :disabled="deleting" @click="submitDelete">{{ deleting ? "删除中" : "确认删除" }}</button>
        </div>
      </div>
    </div>

    <div v-if="groupDialogVisible" class="ll-modal-overlay" @click.self="closeGroupManager">
      <div class="ll-modal ll-modal-md ll-modal-mascot">
        <div class="ll-mascot-side">
          <img class="ll-mascot-img" :src="HERO_MASCOT_DATA_URI" alt="" draggable="false" />
          <div class="ll-mascot-bubble">建好分组后，添加友链或审核邀请时就能把朋友归类啦～</div>
        </div>
        <div class="ll-modal-main">
        <div class="ll-modal-header">
          <h3 class="ll-modal-title">友链分组管理</h3>
          <button class="ll-modal-close" @click="closeGroupManager">
            <svg width="20" height="20" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 5l10 10M15 5l-10 10" /></svg>
          </button>
        </div>
        <div class="ll-modal-body">
          <div class="ll-field">
            <label class="ll-label">{{ groupForm.gid ? "编辑分组名称" : "新增分组" }}</label>
            <div class="ll-group-form">
              <input v-model="groupForm.displayName" type="text" class="ll-input" placeholder="分组名称，如「技术博客」" @keyup.enter="submitGroup" />
              <button class="ll-btn ll-btn-primary ll-btn-tall" :disabled="groupSaving" @click="submitGroup">{{ groupForm.gid ? "保存" : "新增" }}</button>
              <button v-if="groupForm.gid" class="ll-btn ll-btn-ghost ll-btn-tall" @click="groupForm = { gid: 0, displayName: '' }">取消编辑</button>
            </div>
          </div>
          <div v-if="groups.length" class="ll-group-list">
            <div v-for="g in groups" :key="g.gid" class="ll-group-row">
              <span class="ll-group-name">{{ g.displayName }}</span>
              <span class="ll-group-slug">{{ g.name }}</span>
              <div class="ll-group-ops">
                <button class="ll-btn ll-btn-ghost" @click="editGroup(g)">编辑</button>
                <button class="ll-btn ll-btn-danger" @click="deleteGroupConfirm(g)">删除</button>
              </div>
            </div>
          </div>
        </div>
        <div class="ll-modal-footer">
          <button class="ll-btn ll-btn-ghost" @click="closeGroupManager">关闭</button>
        </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.ll-wrap { display: flex; flex-direction: column; height: 100%; min-height: 0; padding: 16px 20px; box-sizing: border-box; }
.ll-scroll { flex: 1; min-height: 0; overflow-y: auto; padding: 0 4px; position: relative; }
.ll-loading { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; }
.ll-loading .uv-loader { width: 80px; height: 50px; position: relative; }
.ll-loading .uv-loader-text { position: absolute; top: 0; color: #c8b6ff; font-size: .8rem; }
.ll-loading .uv-load { background-color: #9a79ff; border-radius: 50px; display: block; height: 16px; width: 16px; bottom: 0; position: absolute; }
.ll-empty { padding: 48px 0; flex: 1; display: flex; align-items: center; justify-content: center; min-height: 200px; }

.ll-table-wrap { position: relative; flex: 1; min-height: 0; overflow: auto; display: flex; flex-direction: column; }
.ll-table { display: flex; flex-direction: column; min-width: 0; gap: 8px; padding: 4px 0; flex: 1; min-height: 100%; }
.ll-row { display: grid; grid-template-columns: 46px minmax(140px,1fr) minmax(120px,150px) minmax(150px,1fr) minmax(90px,120px) 90px 90px 140px 160px; gap: 12px; align-items: center; padding: 10px 14px; border-radius: 20px; background: transparent; border: 1px solid rgba(0,0,0,.05); box-shadow: 0 2px 8px rgba(0,0,0,.03); box-sizing: border-box; height: 60px; }
.ll-row:hover { box-shadow: 0 4px 14px rgba(0,0,0,.06); }
.ll-row--head { height: 36px; padding: 0 14px; background: transparent; border: none; box-shadow: none; }
.ll-row--head:hover { box-shadow: none; }
.ll-row--head > div { font-size: 11px; font-weight: 700; color: #94a3b8; letter-spacing: .04em; }
.ll-row--pending { border-color: rgba(147,197,253,.3); }
.ll-row--ok { border-color: rgba(134,239,172,.3); }
.ll-row--warn { border-color: rgba(252,165,165,.3); opacity: .85; }
.avatar-cell,.desc-cell,.rss-cell,.group-cell,.status-cell,.source-cell,.time-cell { font-size: 12px; line-height: 1.45; color: #475569; display: flex; flex-direction: column; justify-content: center; align-items: center; text-align: center; }
.name-cell { font-size: 12px; line-height: 1.45; color: #475569; display: flex; flex-direction: column; justify-content: center; align-items: flex-start; text-align: left; min-width: 0; }
.avatar-cell { align-items: flex-start; }
.action-cell { display: flex; flex-direction: row; gap: 8px; justify-content: center; align-items: center; flex-wrap: wrap; }
.site-avatar { width: 34px; height: 34px; border-radius: 10px; object-fit: cover; background: #f1f5f9; border: 1px solid #e2e8f0; flex-shrink: 0; }
.site-name { font-size: 13px; font-weight: 600; color: #0f172a; line-height: 1.3; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 100%; }
.site-url,.contact-rss { color: #94a3b8; word-break: break-all; font-size: 12px; line-height: 1.45; }
.rss-cell,.group-cell { min-width: 0; word-break: break-word; }
.external-link { display: inline-block; text-decoration: none; transition: color .15s ease, text-decoration-color .15s ease; text-decoration-line: underline; text-decoration-color: transparent; text-underline-offset: 3px; }
.external-link:hover,.external-link:focus-visible { color: #4f46e5; text-decoration-color: currentColor; }
.external-link:focus-visible { outline: 2px solid rgba(79,70,229,.35); outline-offset: 2px; border-radius: 4px; }
.desc-cell { overflow: hidden; }
.desc-text { font-size: 12px; color: #64748b; line-height: 1.45; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 100%; }
.desc-icon-trigger { position: relative; display: inline-flex; align-items: center; justify-content: center; color: #94a3b8; cursor: pointer; transition: color .15s; }
.desc-icon-trigger:hover { color: #4f46e5; }
.ll-desc-tooltip { position: fixed; padding: 8px 12px; background: rgba(255,255,255,.95); backdrop-filter: blur(8px); color: #334155; font-size: 11px; line-height: 1.55; border-radius: 10px; border: 1px solid rgba(203,213,225,.8); white-space: normal; word-break: break-word; width: 220px; z-index: 100; box-shadow: 0 8px 24px rgba(0,0,0,.08); text-align: left; opacity: 0; pointer-events: none; transition: opacity .16s ease; }
.desc-icon-trigger:hover .ll-desc-tooltip,.desc-icon-trigger:focus-visible .ll-desc-tooltip { opacity: 1; pointer-events: auto; }
.status-cell { gap: 6px; }
.status-pill { display: inline-flex; align-items: center; justify-content: center; min-height: 24px; padding: 2px 10px; border-radius: 14px; font-size: 11px; font-weight: 700; line-height: 1.2; text-align: center; }
.status-pill.pending { background: #eff6ff; color: #2563eb; }
.status-pill.ok { background: #ecfdf5; color: #047857; }
.status-pill.warn { background: #fef2f2; color: #b91c1c; }
.source-text { font-size: 11px; color: #9ca3af; }
.muted { color: #94a3b8; }
.ll-row .ll-group-tag { font-size: 11px; padding: 2px 8px; border-radius: 999px; font-weight: 600; background: #eef2ff; color: #4f46e5; max-width: 100%; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.action-btn { outline: none; padding: 5px 12px; border: 2px dashed #64748b; border-radius: 15px; background-color: #f1f5f9; color: #64748b; font-size: 11px; font-weight: 600; cursor: pointer; transition: transform .2s ease-out; box-shadow: 0 0 0 3px #f1f5f9, 1.5px 1.5px 3px 1px rgba(0,0,0,.15); white-space: nowrap; }
.action-btn:hover { transform: translateY(-4px) translateX(-2px); box-shadow: 0 0 0 3px #f1f5f9, 2px 5px 0 0 currentColor; }
.action-btn:active { transform: translateY(1px) translateX(1px); box-shadow: 0 0 0 3px #f1f5f9, 0 0 0 0 currentColor; }
.action-btn:disabled { opacity: .5; cursor: not-allowed; transform: none; }
.action-btn.approve { border-color: #047857; color: #047857; background-color: #ecfdf5; box-shadow: 0 0 0 3px #ecfdf5, 1.5px 1.5px 3px 1px rgba(0,0,0,.15); }
.action-btn.approve:hover { box-shadow: 0 0 0 3px #ecfdf5, 2px 5px 0 0 #047857; }
.action-btn.reject { border-color: #b91c1c; color: #b91c1c; background-color: #fef2f2; box-shadow: 0 0 0 3px #fef2f2, 1.5px 1.5px 3px 1px rgba(0,0,0,.15); }
.action-btn.reject:hover { box-shadow: 0 0 0 3px #fef2f2, 2px 5px 0 0 #b91c1c; }
.action-btn.delete { border-color: #64748b; color: #64748b; background-color: #f8fafc; box-shadow: 0 0 0 3px #f8fafc, 1.5px 1.5px 3px 1px rgba(0,0,0,.15); }
.action-btn.delete:hover { box-shadow: 0 0 0 3px #f8fafc, 2px 5px 0 0 #64748b; }
.ll-table-wrap.is-scrolling .ll-row,.ll-table-wrap.is-scrolling .action-btn { pointer-events: none; }
.ll-btn { display: inline-flex; align-items: center; gap: 4px; padding: 6px 12px; border-radius: 8px; font-size: 12px; font-weight: 500; cursor: pointer; border: 1px solid transparent; transition: all .15s; white-space: nowrap; }
.ll-btn:disabled { opacity: .6; cursor: not-allowed; }
.ll-btn-primary { background: #0061a4; color: #fff; }
.ll-btn-primary:hover:not(:disabled) { background: #004e85; }
.ll-btn-danger { background: #fff; color: #ef4444; border-color: #fecaca; }
.ll-btn-danger:hover:not(:disabled) { background: #fef2f2; }
.ll-btn-ghost { background: #f3f4f6; color: #4b5563; }
.ll-btn-ghost:hover:not(:disabled) { background: #e5e7eb; }
.ll-modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,.5); display: flex; align-items: center; justify-content: center; z-index: 9999; padding: 20px; }
.ll-modal { background: #fff; border-radius: 16px; width: 100%; max-width: 460px; max-height: 90vh; overflow-y: auto; }
.ll-modal-sm { max-width: 420px; }
.ll-modal-md { max-width: 720px; }
.ll-modal-lg { max-width: 920px; }
.ll-modal-mascot { display: flex; align-items: stretch; overflow: hidden; }
.ll-modal-mascot .ll-modal-main { flex: 1; min-width: 0; display: flex; flex-direction: column; max-height: 90vh; overflow-y: auto; }
.ll-mascot-side { flex-shrink: 0; width: 200px; background: linear-gradient(160deg, #eef2ff 0%, #f8fafc 100%); border-right: 1px solid #eef2f7; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 10px; padding: 16px 14px; }
.ll-mascot-img { width: 110px; height: auto; object-fit: contain; user-select: none; -webkit-user-drag: none; filter: drop-shadow(0 8px 18px rgba(79,70,229,.18)); }
.ll-modal-md .ll-mascot-img { width: 82px; }
.ll-modal-md .ll-mascot-side { width: 168px; }
.ll-mascot-bubble { position: relative; background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 10px 12px; font-size: 12px; line-height: 1.6; color: #4b5563; box-shadow: 0 6px 16px rgba(15,23,42,.08); }
.ll-mascot-bubble::after { content: ""; position: absolute; top: -7px; left: 28px; width: 12px; height: 12px; background: #fff; border-left: 1px solid #e5e7eb; border-top: 1px solid #e5e7eb; transform: rotate(45deg); }
@media (max-width: 700px) { .ll-mascot-side { display: none; } }
.ll-modal-header { display: flex; justify-content: space-between; align-items: center; padding: 14px 20px 8px; }
.ll-modal-title { font-size: 16px; font-weight: 600; color: #1f2937; margin: 0; }
.ll-modal-close { width: 30px; height: 30px; display: flex; align-items: center; justify-content: center; background: none; border: none; cursor: pointer; border-radius: 6px; color: #6b7280; }
.ll-modal-close:hover { background: #f3f4f6; }
.ll-modal-body { padding: 8px 20px 16px; }
.ll-modal-footer { display: flex; justify-content: flex-end; gap: 10px; padding: 8px 20px 16px; }
.ll-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px 14px; margin-bottom: 10px; }
.ll-field { margin-bottom: 10px; }
.ll-grid .ll-field { margin-bottom: 0; }
.ll-label { display: block; font-size: 12px; font-weight: 500; color: #374151; margin-bottom: 5px; }
.ll-req { color: #ef4444; margin-left: 2px; }
.ll-input { width: 100%; padding: 8px 11px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 13px; color: #1f2937; box-sizing: border-box; }
.ll-input:focus { outline: none; border-color: #0061a4; box-shadow: 0 0 0 3px rgba(0,97,164,.1); }
.ll-textarea { resize: vertical; min-height: 60px; }
.ll-select { position: relative; }
.ll-select-trigger { width: 100%; padding: 8px 11px; border: 1px solid #d1d5db; border-radius: 8px; background: #fff; font-size: 13px; color: #1f2937; cursor: pointer; display: flex; align-items: center; justify-content: space-between; gap: 8px; box-sizing: border-box; transition: border-color .15s, box-shadow .15s; }
.ll-select-trigger:hover { border-color: #9ca3af; }
.ll-select.open .ll-select-trigger { border-color: #0061a4; box-shadow: 0 0 0 3px rgba(0,97,164,.1); }
.ll-select-arrow { width: 16px; height: 16px; color: #6b7280; transition: transform .16s; flex-shrink: 0; }
.ll-select-arrow.open { transform: rotate(180deg); }
.ll-select-menu { position: absolute; left: 0; right: 0; top: calc(100% + 6px); z-index: 20; padding: 6px; background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; box-shadow: 0 12px 32px rgba(15,23,42,.14); display: flex; flex-direction: column; gap: 2px; max-height: 240px; overflow-y: auto; }
.ll-select-option { width: 100%; padding: 8px 10px; border: none; border-radius: 7px; background: transparent; text-align: left; font-size: 13px; color: #374151; cursor: pointer; transition: background .12s, color .12s; }
.ll-select-option:hover { background: #f3f4f6; }
.ll-select-option.active { background: #eef2ff; color: #4f46e5; font-weight: 600; }
.ll-confirm-text { font-size: 13px; color: #4b5563; line-height: 1.6; margin: 0 0 16px; }
.ll-group-tag { font-size: 11px; padding: 2px 8px; border-radius: 999px; font-weight: 500; background: #eef2ff; color: #4f46e5; }
.ll-group-form { display: flex; gap: 8px; align-items: stretch; }
.ll-group-form .ll-input { flex: 1; }
.ll-btn-tall { padding-top: 0; padding-bottom: 0; }
.ll-group-list { display: flex; flex-direction: column; gap: 8px; margin-top: 8px; }
.ll-group-row { display: flex; align-items: center; gap: 10px; padding: 10px 12px; background: #f8fafc; border: 1px solid #e5e7eb; border-radius: 10px; }
.ll-group-name { font-size: 14px; font-weight: 600; color: #1f2937; }
.ll-group-slug { font-size: 12px; color: #9ca3af; flex: 1; }
.ll-group-ops { display: flex; gap: 6px; }
</style>
