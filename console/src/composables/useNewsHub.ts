import { apiGet } from "../api";

export interface NewsItem {
  id: string;
  sourceId: string;
  title: string;
  summary: string;
  url: string;
  publishedAt: string;
  blogTitle: string;
  blogUrl: string;
  blogLogo: string;
  tags?: string[];
  mentionCount?: number;
  sourceSiteCount?: number;
}

export interface NewsBrowseResponse {
  generatedAt: string;
  total: number;
  cursor: string;
  nextCursor: string;
  hasMore: boolean;
  refreshing: boolean;
  indexedBlogs?: number;
  indexedFeeds?: number;
  indexedItems?: number;
  items: NewsItem[];
}

export interface NewsDiscoverItem {
  sourceId: string;
  blogTitle: string;
  blogUrl: string;
  blogDescription?: string;
  blogLogo: string;
  rssUrl?: string;
  latestTitle?: string;
  latestUrl?: string;
  latestPublishedAt?: string;
  itemCount?: number;
  mentionCount?: number;
  tags?: string[];
}

export interface NewsDiscoverResponse {
  generatedAt: string;
  total: number;
  cursor: string;
  nextCursor: string;
  hasMore: boolean;
  refreshing: boolean;
  items: NewsDiscoverItem[];
}

function qs(params: Record<string, string | number | undefined>): string {
  const usp = new URLSearchParams();
  for (const [k, v] of Object.entries(params)) {
    if (v === undefined || v === null) continue;
    const t = String(v).trim();
    if (!t) continue;
    usp.append(k, t);
  }
  const s = usp.toString();
  return s ? `&${s}` : "";
}

export async function fetchNewsBrowse(size = 24, cursor = "", onlyMyGalaxy = false): Promise<NewsBrowseResponse> {
  const res = await apiGet<NewsBrowseResponse>(`newsBrowse${qs({ size, cursor, onlyMyGalaxy: onlyMyGalaxy ? "true" : "" })}`);
  if (!res.ok) throw new Error(res.message || "读取资讯失败");
  return res.data;
}

export async function fetchNewsSearch(q: string, size = 24, cursor = "", onlyMyGalaxy = false): Promise<NewsBrowseResponse> {
  const res = await apiGet<NewsBrowseResponse>(`newsSearch${qs({ q, size, cursor, onlyMyGalaxy: onlyMyGalaxy ? "true" : "" })}`);
  if (!res.ok) throw new Error(res.message || "搜索资讯失败");
  return res.data;
}

export async function fetchNewsDiscover(size = 24, cursor = ""): Promise<NewsDiscoverResponse> {
  const res = await apiGet<NewsDiscoverResponse>(`newsDiscover${qs({ size, cursor })}`);
  if (!res.ok) throw new Error(res.message || "读取站点发现失败");
  return res.data;
}
