import { apiPost } from "../api";

export interface RegisterPayload {
  hubBaseUrl: string;
  invitationCode: string;
  siteName: string;
  siteUrl: string;
  siteDescription: string;
  siteRssUrl: string;
  siteAvatarUrl: string;
  contactEmail: string;
  siteNodeName: string;
  siteNodeAvatar: string;
}

export interface RegisterResult {
  success: boolean;
  status: number;
  message: string;
  siteId: string;
  createdAt: string;
  nodeName: string;
  category: string;
  nodeAvatar: string;
  hasApiKey: boolean;
}

/** 申请签发码 */
export async function requestInvitationCode(payload: {
  hubBaseUrl: string;
  contactEmail: string;
  siteUrl: string;
}): Promise<{ success: boolean; message: string; expiresAt: string }> {
  const res = await apiPost<{ expiresAt?: string }>("requestCode", payload);
  if (!res.ok) {
    throw new Error(res.message || "申请签发码失败");
  }
  return { success: true, message: res.message, expiresAt: String(res.data.expiresAt || "") };
}

/** 带签发码注册 */
export async function registerWithInvitation(payload: RegisterPayload): Promise<RegisterResult> {
  const res = await apiPost<{
    siteId?: string;
    createdAt?: string;
    nodeName?: string;
    category?: string;
    nodeAvatar?: string;
    hasApiKey?: boolean;
  }>("register", payload as unknown as Record<string, unknown>);
  if (!res.ok) {
    throw new Error(res.message || "接入星链失败");
  }
  const d = res.data;
  return {
    success: true,
    status: res.status,
    message: res.message,
    siteId: String(d.siteId || ""),
    createdAt: String(d.createdAt || ""),
    nodeName: String(d.nodeName || d.category || ""),
    category: String(d.category || ""),
    nodeAvatar: String(d.nodeAvatar || ""),
    hasApiKey: Boolean(d.hasApiKey),
  };
}

/** 发送登舱验证码 */
export async function sendBoardingCode(payload: {
  hubBaseUrl: string;
  contactEmail: string;
}): Promise<{ success: boolean; message: string; expiresAt: string }> {
  const res = await apiPost<{ expiresAt?: string }>("boardingSend", payload);
  if (!res.ok) {
    throw new Error(res.message || "发送验证码失败");
  }
  return { success: true, message: res.message, expiresAt: String(res.data.expiresAt || "") };
}

export interface BoardingRestoreResult {
  success: boolean;
  message: string;
  siteId: string;
  siteName: string;
  siteUrl: string;
  contactEmail: string;
  description: string;
  rssUrl: string;
  nodeName: string;
  category: string;
  nodeAvatar: string;
  createdAt: string;
  hasApiKey: boolean;
}

/** 验证码恢复站点 */
export async function restoreByBoardingCode(payload: {
  hubBaseUrl: string;
  contactEmail: string;
  code: string;
}): Promise<BoardingRestoreResult> {
  const res = await apiPost<Record<string, string | boolean>>("boardingRestore", payload);
  if (!res.ok) {
    throw new Error(res.message || "登舱恢复失败");
  }
  const d = res.data;
  return {
    success: true,
    message: res.message,
    siteId: String(d.siteId || ""),
    siteName: String(d.siteName || ""),
    siteUrl: String(d.siteUrl || ""),
    contactEmail: String(d.contactEmail || ""),
    description: String(d.description || ""),
    rssUrl: String(d.rssUrl || ""),
    nodeName: String(d.nodeName || d.category || ""),
    category: String(d.category || ""),
    nodeAvatar: String(d.nodeAvatar || ""),
    createdAt: String(d.createdAt || ""),
    hasApiKey: Boolean(d.hasApiKey),
  };
}
