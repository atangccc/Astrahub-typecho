import { apiGet, apiPost } from "../api";
import type { FriendInvitationItem } from "../types";

export type FriendBox = "inbox" | "outbox";

export interface FriendInvitationsResponse {
  success: boolean;
  generatedAt: string;
  total: number;
  items: FriendInvitationItem[];
}

export async function fetchFriendInvitations(box: FriendBox, status = ""): Promise<FriendInvitationsResponse> {
  const params = new URLSearchParams();
  params.set("box", box);
  if (status.trim()) params.set("status", status.trim());
  const res = await apiGet<FriendInvitationsResponse>(`friendList&${params.toString()}`);
  if (!res.ok) {
    throw new Error(res.message || `读取友链邀请失败（${res.status}）`);
  }
  return {
    success: true,
    generatedAt: String(res.data.generatedAt || ""),
    total: Number(res.data.total || 0),
    items: Array.isArray(res.data.items) ? res.data.items : [],
  };
}

export async function createFriendInvitation(toSiteId: string, message = "", linkGroupName = "") {
  const res = await apiPost<{ invitation?: FriendInvitationItem }>("friendCreate", {
    toSiteId,
    message,
    linkGroupName,
  });
  if (!res.ok) throw new Error(res.message || "发起邀请失败");
  return res.data.invitation || null;
}

export async function reviewFriendInvitation(inviteId: string, approved: boolean, reason = "", linkGroupName = "") {
  const res = await apiPost<{ invitation?: FriendInvitationItem }>("friendReview", {
    inviteId,
    approved,
    reason,
    linkGroupName,
  });
  if (!res.ok) throw new Error(res.message || "审核失败");
  return res.data.invitation || null;
}

export async function ackFriendInvitation(inviteId: string, lastError = "") {
  const res = await apiPost("friendAck", { inviteId, lastError });
  if (!res.ok) throw new Error(res.message || "回执失败");
}

export async function cancelFriendInvitation(inviteId: string) {
  const res = await apiPost("friendCancel", { inviteId });
  if (!res.ok) throw new Error(res.message || "撤回失败");
}

export async function deleteFriendInvitation(inviteId: string) {
  const res = await apiPost("friendDelete", { inviteId });
  if (!res.ok) throw new Error(res.message || "删除失败");
}

/** 读取本地友链分组选项（邀请/审核下拉用）。 */
export interface LinkGroupOption {
  name: string;
  displayName: string;
}

export async function fetchFriendInvitationLinkGroups(): Promise<LinkGroupOption[]> {
  const res = await apiGet<{ items?: LinkGroupOption[] }>("linkGroups");
  if (!res.ok) return [];
  const items = Array.isArray(res.data.items) ? res.data.items : [];
  return items.map((g) => ({ name: String(g.name || ""), displayName: String(g.displayName || g.name || "") }));
}

/** 主动解除友链关系。 */
export async function removeFriendRelation(peerSiteId: string, reason = "") {
  const res = await apiPost<{ removed?: boolean; peerSiteUrl?: string }>("friendRemoveRelation", {
    peerSiteId,
    reason,
  });
  if (!res.ok) throw new Error(res.message || "解除友链关系失败");
  return { removed: Boolean(res.data.removed), peerSiteUrl: String(res.data.peerSiteUrl || "") };
}

/** 本地建链：把对端站点写入本地友链表（接受邀请后调用，幂等）。 */
export async function removeOwnFriendFollow(peerSiteId: string) {
  const res = await apiPost<{ removed?: boolean; peerSiteUrl?: string }>("friendRemoveFollow", {
    peerSiteId,
  });
  if (!res.ok) throw new Error(res.message || "删除友链失败");
  return { removed: Boolean(res.data.removed), peerSiteUrl: String(res.data.peerSiteUrl || "") };
}

export async function reconcileFriendInvitation(invitation: FriendInvitationItem, currentSiteId: string) {
  const peer =
    invitation.fromSite?.siteId === currentSiteId ? invitation.toSite : invitation.fromSite;
  const res = await apiPost("friendReconcile", {
    relationKind: "mutual",
    peerSiteId: peer?.siteId || "",
    peerSiteName: peer?.siteName || "",
    peerSiteUrl: peer?.siteUrl || "",
    peerDescription: peer?.description || "",
    peerAvatarUrl: peer?.avatarUrl || "",
    peerRssUrl: peer?.rssUrl || "",
    linkGroupName: invitation.linkGroupName || "",
  });
  if (!res.ok) throw new Error(res.message || "本地建链失败");
  return res.data;
}
