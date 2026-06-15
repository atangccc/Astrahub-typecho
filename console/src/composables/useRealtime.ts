import { onBeforeUnmount, watch } from "vue";
import type { Ref } from "vue";
import { apiPost } from "../api";
import type { AstraHubSettings, AstraHubRealtimeTokenResult, FriendInvitationItem } from "../types";

const RECONNECT_DELAY_MS = 3000;

export interface HubRealtimeEvent<T = unknown> {
  id?: string;
  type: string;
  timestamp?: string;
  data?: T;
}

interface HubSiteRelationUpdatedPayload {
  sourceSiteId?: string;
  impactedSiteIds?: string[];
  trigger?: string;
  inviteId?: string;
}

const HUB_INVITATION_EVENT_TYPES = new Set<string>([
  "friend_invitation_created",
  "friend_invitation_reviewed",
  "friend_invitation_acked",
  "friend_invitation_cancelled",
  "friend_invitation_deleted",
  "friend_relation_removed",
  "site_relation_updated",
  "site_profile_updated",
]);

/** 换取 ws-token */
export async function issueRealtimeToken(): Promise<AstraHubRealtimeTokenResult> {
  const res = await apiPost<{ token?: string; expiresAt?: string }>("wsToken");
  if (!res.ok || !String(res.data.token || "").trim()) {
    throw new Error(res.message || `获取实时连接令牌失败（${res.status}）`);
  }
  return {
    success: true,
    status: res.status,
    message: res.message || "ok",
    token: String(res.data.token || ""),
    expiresAt: String(res.data.expiresAt || ""),
  };
}

/** 由 hubBaseUrl + token 拼出 wss 直连地址。 */
export function buildHubWsUrl(rawBaseUrl: string, token: string): string {
  const value = String(rawBaseUrl || "").trim().replace(/\/+$/, "");
  const accessToken = String(token || "").trim();
  if (!value || !accessToken) {
    return "";
  }
  try {
    const url = new URL(value);
    url.protocol = url.protocol === "https:" ? "wss:" : "ws:";
    url.pathname = "/v1/ws";
    url.search = "";
    url.hash = "";
    url.searchParams.set("access_token", accessToken);
    return url.toString();
  } catch {
    return "";
  }
}

function isRelevantHubEvent(event: HubRealtimeEvent<unknown>, currentSiteId: string): boolean {
  if (!HUB_INVITATION_EVENT_TYPES.has(event.type)) {
    return false;
  }
  const siteId = String(currentSiteId || "").trim();
  if (!siteId) {
    return false;
  }
  if (event.type === "site_relation_updated") {
    const data = (event.data || {}) as HubSiteRelationUpdatedPayload;
    if (String(data.sourceSiteId || "").trim() === siteId) {
      return true;
    }
    const impacted = Array.isArray(data.impactedSiteIds) ? data.impactedSiteIds : [];
    return impacted.some((id) => String(id || "").trim() === siteId);
  }
  if (event.type === "friend_relation_removed") {
    const data = (event.data || {}) as { actorSiteId?: string; peerSiteId?: string };
    return (
      String(data.actorSiteId || "").trim() === siteId ||
      String(data.peerSiteId || "").trim() === siteId
    );
  }
  if (event.type === "site_profile_updated") {
    const data = (event.data || {}) as { siteId?: string };
    return String(data.siteId || "").trim() !== "";
  }
  const invitation = event.data as FriendInvitationItem | undefined;
  if (!invitation) {
    return false;
  }
  return (
    String(invitation.fromSite?.siteId || "").trim() === siteId ||
    String(invitation.toSite?.siteId || "").trim() === siteId
  );
}

export function useRealtime(
  settings: Ref<AstraHubSettings>,
  onRelevantEvent: (event: HubRealtimeEvent<unknown>) => void
) {
  let socket: WebSocket | null = null;
  let reconnectTimer: ReturnType<typeof setTimeout> | null = null;
  let stopped = false;

  const clearReconnectTimer = () => {
    if (reconnectTimer) {
      clearTimeout(reconnectTimer);
      reconnectTimer = null;
    }
  };

  const closeSocket = () => {
    if (!socket) {
      return;
    }
    socket.onopen = null;
    socket.onclose = null;
    socket.onerror = null;
    socket.onmessage = null;
    socket.close();
    socket = null;
  };

  const scheduleReconnect = () => {
    clearReconnectTimer();
    reconnectTimer = setTimeout(() => {
      void connect();
    }, RECONNECT_DELAY_MS);
  };

  const connect = async () => {
    if (stopped || socket) {
      return;
    }
    const hubBaseUrl = String(settings.value.connection.hubBaseUrl || "").trim();
    const currentSiteId = String(settings.value.credentials.siteId || "").trim();
    if (!hubBaseUrl || !currentSiteId) {
      return;
    }
    let ticket: AstraHubRealtimeTokenResult;
    try {
      ticket = await issueRealtimeToken();
    } catch {
      if (!stopped) {
        scheduleReconnect();
      }
      return;
    }
    if (stopped || socket) {
      return;
    }
    const wsUrl = buildHubWsUrl(hubBaseUrl, String(ticket.token || "").trim());
    if (!wsUrl) {
      scheduleReconnect();
      return;
    }
    const ws = new WebSocket(wsUrl);
    socket = ws;
    ws.onmessage = (messageEvent) => {
      try {
        const event = JSON.parse(String(messageEvent.data)) as HubRealtimeEvent<unknown>;
        if (isRelevantHubEvent(event, currentSiteId)) {
          onRelevantEvent(event);
        }
      } catch {
        // ignore malformed
      }
    };
    ws.onclose = () => {
      socket = null;
      if (!stopped) {
        scheduleReconnect();
      }
    };
    ws.onerror = () => {
      closeSocket();
    };
  };

  const reconnect = () => {
    stopped = false;
    clearReconnectTimer();
    closeSocket();
    void connect();
  };

  const stop = () => {
    stopped = true;
    clearReconnectTimer();
    closeSocket();
  };

  watch(
    () => [settings.value.connection.hubBaseUrl, settings.value.credentials.siteId].join("|"),
    () => reconnect(),
    { immediate: true }
  );

  onBeforeUnmount(stop);

  return { reconnect, stop };
}
