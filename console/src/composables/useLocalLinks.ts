import { ref } from "vue";
import { apiGet, apiPost } from "../api";
import type { LocalLinkItem } from "../types";

export function useLocalLinks() {
  const loading = ref(false);
  const items = ref<LocalLinkItem[]>([]);
  const error = ref("");

  function normalize(raw: Record<string, unknown>): LocalLinkItem {
    return {
      flid: Number(raw.flid ?? raw.lid ?? 0),
      siteName: String(raw.siteName ?? raw.name ?? ""),
      siteUrl: String(raw.siteUrl ?? raw.url ?? ""),
      avatarUrl: String(raw.avatarUrl ?? raw.image ?? ""),
      summary: String(raw.summary ?? raw.description ?? ""),
      rssUrl: String(raw.rssUrl ?? ""),
      applicantEmail: String(raw.applicantEmail ?? raw.email ?? ""),
      targetSiteId: String(raw.targetSiteId ?? ""),
      relationKind: String(raw.relationKind ?? "none"),
      groupId: Number(raw.groupId ?? 0),
      reviewState: Number(raw.reviewState ?? 1),
      sourceType: String(raw.sourceType ?? "admin"),
      applicantIp: String(raw.applicantIp ?? ""),
      sortOrder: Number(raw.sortOrder ?? raw.order ?? 0),
      createdAt: Number(raw.createdAt ?? 0),
      updatedAt: Number(raw.updatedAt ?? 0),
    };
  }

  async function fetchAll(options?: { silent?: boolean }): Promise<void> {
    const silent = options?.silent === true;
    if (!silent) {
      loading.value = true;
    }
    error.value = "";
    try {
      const res = await apiGet<{ items?: Record<string, unknown>[] }>("linksList");
      if (!res.ok) {
        throw new Error(res.message || "读取友链失败");
      }
      const list = Array.isArray(res.data.items) ? res.data.items : [];
      items.value = list.map(normalize);
    } catch (e) {
      if (!silent) {
        error.value = e instanceof Error ? e.message : "读取友链失败";
        items.value = [];
      }
    } finally {
      if (!silent) {
        loading.value = false;
      }
    }
  }

  async function saveLink(payload: Partial<LocalLinkItem>): Promise<{ ok: boolean; message: string }> {
    const res = await apiPost("linkSave", payload as Record<string, unknown>);
    if (res.ok) {
      await fetchAll();
    }
    return { ok: res.ok, message: res.message };
  }

  async function deleteLink(flid: number): Promise<boolean> {
    const res = await apiPost("linkDelete", { flid });
    if (res.ok) {
      await fetchAll();
    }
    return res.ok;
  }

  async function approveLink(flid: number): Promise<boolean> {
    const res = await apiPost("linkApprove", { flid });
    if (res.ok) {
      await fetchAll();
    }
    return res.ok;
  }

  async function rejectLink(flid: number, reason: string): Promise<boolean> {
    const res = await apiPost("linkReject", { flid, reason });
    if (res.ok) {
      await fetchAll();
    }
    return res.ok;
  }

  return {
    loading,
    items,
    error,
    fetchAll,
    saveLink,
    deleteLink,
    approveLink,
    rejectLink,
  };
}
