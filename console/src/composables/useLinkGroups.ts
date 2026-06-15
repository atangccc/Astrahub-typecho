import { ref } from "vue";
import { apiGet, apiPost } from "../api";

export interface LinkGroupItem {
  gid: number;
  name: string;
  displayName: string;
  sortOrder: number;
  createdAt: number;
  updatedAt: number;
}

export function useLinkGroups() {
  const loading = ref(false);
  const items = ref<LinkGroupItem[]>([]);
  const error = ref("");

  function normalize(raw: Record<string, unknown>): LinkGroupItem {
    return {
      gid: Number(raw.gid ?? 0),
      name: String(raw.name ?? ""),
      displayName: String(raw.displayName ?? raw.name ?? ""),
      sortOrder: Number(raw.sortOrder ?? 0),
      createdAt: Number(raw.createdAt ?? 0),
      updatedAt: Number(raw.updatedAt ?? 0),
    };
  }

  async function fetchAll(): Promise<void> {
    loading.value = true;
    error.value = "";
    try {
      const res = await apiGet<{ items?: Record<string, unknown>[] }>("linkGroups&as=manage");
      if (!res.ok) {
        throw new Error(res.message || "读取分组失败");
      }
      const list = Array.isArray(res.data.items) ? res.data.items : [];
      items.value = list.map(normalize);
    } catch (e) {
      error.value = e instanceof Error ? e.message : "读取分组失败";
      items.value = [];
    } finally {
      loading.value = false;
    }
  }

  async function saveGroup(payload: { gid?: number; displayName: string; name?: string }): Promise<{ ok: boolean; message: string }> {
    const res = await apiPost("linkGroupSave", payload as Record<string, unknown>);
    if (res.ok) {
      await fetchAll();
    }
    return { ok: res.ok, message: res.message };
  }

  async function deleteGroup(gid: number): Promise<boolean> {
    const res = await apiPost("linkGroupDelete", { gid });
    if (res.ok) {
      await fetchAll();
    }
    return res.ok;
  }

  async function reorderGroups(gids: number[]): Promise<boolean> {
    const res = await apiPost("linkGroupsReorder", { gids });
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
    saveGroup,
    deleteGroup,
    reorderGroups,
  };
}
