let ACTION_URL = "/action/astrahub";

export function configureApi(actionUrl: string) {
  if (actionUrl && actionUrl.trim()) {
    ACTION_URL = actionUrl.trim();
  }
}

function buildUrl(doAction: string): string {
  // 只对 action 名做 encodeURIComponent，查询串原样拼接：整串编码会把 & = 变成 %26 %3D，
  // 导致后端 $request->get('do') 读到整串而落入 default 报 unknown action。
  const ampIdx = doAction.indexOf("&");
  const action = ampIdx >= 0 ? doAction.slice(0, ampIdx) : doAction;
  const rest = ampIdx >= 0 ? doAction.slice(ampIdx + 1) : "";
  const sep = ACTION_URL.indexOf("?") >= 0 ? "&" : "?";
  let url = `${ACTION_URL}${sep}do=${encodeURIComponent(action)}`;
  if (rest) {
    url += `&${rest}`;
  }
  return url;
}

export function buildAvatarProxyUrl(remoteUrl: string): string {
  const sep = ACTION_URL.indexOf("?") >= 0 ? "&" : "?";
  return `${ACTION_URL}${sep}do=graphAvatar&url=${encodeURIComponent(remoteUrl)}`;
}

export interface ApiResult<T = Record<string, unknown>> {
  ok: boolean;
  status: number;
  data: T;
  message: string;
}

async function parseJson(response: Response): Promise<Record<string, unknown>> {
  const text = await response.text();
  if (!text) {
    return {};
  }
  try {
    return JSON.parse(text) as Record<string, unknown>;
  } catch {
    return {};
  }
}

export async function apiGet<T = Record<string, unknown>>(doAction: string): Promise<ApiResult<T>> {
  const response = await fetch(buildUrl(doAction), {
    method: "GET",
    headers: { Accept: "application/json" },
    credentials: "same-origin",
  });
  const payload = await parseJson(response);
  return {
    ok: response.ok && payload.success !== false,
    status: response.status,
    data: payload as T,
    message: String((payload as { message?: string }).message || ""),
  };
}

export async function apiPost<T = Record<string, unknown>>(
  doAction: string,
  body?: Record<string, unknown>
): Promise<ApiResult<T>> {
  const response = await fetch(buildUrl(doAction), {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      Accept: "application/json",
    },
    credentials: "same-origin",
    body: JSON.stringify(body || {}),
  });
  const payload = await parseJson(response);
  return {
    ok: response.ok && payload.success !== false,
    status: response.status,
    data: payload as T,
    message: String((payload as { message?: string }).message || ""),
  };
}
