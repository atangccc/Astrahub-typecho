import { ref } from "vue";
import { apiGet, apiPost } from "../api";
import { Toast } from "../toast";
import type { AstraHubSettings, ReadLaterItem } from "../types";

export interface SaveSettingsOptions {
  silentSuccess?: boolean;
}

function createDefaultSettings(): AstraHubSettings {
  return {
    connection: {
      hubBaseUrl: "https://astra.aobp.cn",
      registerToken: "",
      siteName: "",
      siteUrl: "",
      siteDescription: "",
      contactEmail: "",
      siteNodeName: "",
      siteNodeAvatar: "",
      siteRssUrl: "",
    },
    credentials: { siteId: "", apiKey: "", createdAt: "", apiKeyMasked: "", hasApiKey: false },
    invitation: { allowIncomingInvitations: true, allowOutgoingInvitations: true },
    widget: { enabled: true },
    realtimeBroadcast: { enabled: true },
    favorites: { pinnedSiteUrls: [] },
    readLater: { items: [] },
    friendApply: {
      enabled: true,
      notifyEmail: "",
      mailDriver: "phpmail",
      smtpHost: "",
      smtpPort: 587,
      smtpUser: "",
      smtpPass: "",
      smtpSecure: "tls",
    },
  };
}

export function useConfigMap() {
  const loading = ref(false);
  const saving = ref(false);
  const settings = ref<AstraHubSettings>(createDefaultSettings());

  const fetchSettings = async () => {
    loading.value = true;
    try {
      const res = await apiGet<{ data?: Record<string, unknown> }>("config");
      if (!res.ok) {
        throw new Error(res.message || "读取配置失败");
      }
      const data = (res.data.data || {}) as Record<string, unknown>;
      const defaults = createDefaultSettings();
      const rawConnection = (data.connection || {}) as Record<string, unknown>;
      const rawInvitation = (data.invitation || {}) as Record<string, unknown>;
      const rawRealtime = (data.realtimeBroadcast || {}) as Record<string, unknown>;
      const rawCredentials = (data.credentials || {}) as Record<string, unknown>;
      const rawWidget = (data.widget || {}) as Record<string, unknown>;
      const rawFavorites = (data.favorites || {}) as Record<string, unknown>;
      const rawReadLater = (data.readLater || {}) as Record<string, unknown>;
      const rawFriendApply = (data.friendApply || {}) as Record<string, unknown>;

      settings.value = {
        connection: {
          ...defaults.connection,
          ...rawConnection,
          hubBaseUrl: String(rawConnection.hubBaseUrl || "").trim() || defaults.connection.hubBaseUrl,
        },
        widget: { ...defaults.widget, ...rawWidget },
        credentials: {
          siteId: String(rawCredentials.siteId || ""),
          apiKey: rawCredentials.hasApiKey ? "********" : "",
          createdAt: String(rawCredentials.createdAt || ""),
          apiKeyMasked: String(rawCredentials.apiKeyMasked || ""),
          hasApiKey: Boolean(rawCredentials.hasApiKey),
        },
        invitation: {
          allowIncomingInvitations: Boolean(
            rawInvitation.allowIncomingInvitations ?? defaults.invitation.allowIncomingInvitations
          ),
          allowOutgoingInvitations: Boolean(
            rawInvitation.allowOutgoingInvitations ?? defaults.invitation.allowOutgoingInvitations
          ),
        },
        realtimeBroadcast: {
          enabled: Boolean(rawRealtime.enabled ?? defaults.realtimeBroadcast.enabled),
        },
        favorites: {
          pinnedSiteUrls: Array.isArray(rawFavorites.pinnedSiteUrls)
            ? (rawFavorites.pinnedSiteUrls as string[])
            : [],
        },
        readLater: {
          items: Array.isArray(rawReadLater.items) ? (rawReadLater.items as ReadLaterItem[]) : [],
        },
        friendApply: {
          enabled: Boolean(rawFriendApply.enabled ?? defaults.friendApply.enabled),
          notifyEmail: String(rawFriendApply.notifyEmail || ""),
          mailDriver: String(rawFriendApply.mailDriver || "phpmail"),
          smtpHost: String(rawFriendApply.smtpHost || ""),
          smtpPort: Number(rawFriendApply.smtpPort || 587),
          smtpUser: String(rawFriendApply.smtpUser || ""),
          smtpPass: String(rawFriendApply.smtpPass || ""),
          smtpSecure: String(rawFriendApply.smtpSecure || "tls"),
        },
      };
    } catch {
      Toast.error("读取配置失败");
    } finally {
      loading.value = false;
    }
  };

  const saveSettings = async (options: SaveSettingsOptions = {}): Promise<boolean> => {
    saving.value = true;
    try {
      const body = {
        connection: {
          ...settings.value.connection,
          siteDescription: String(settings.value.connection.siteDescription || "").trim(),
          siteNodeAvatar: String(settings.value.connection.siteNodeAvatar || "").trim(),
          siteRssUrl: String(settings.value.connection.siteRssUrl || "").trim(),
        },
        credentials: {
          siteId: settings.value.credentials.siteId,
          createdAt: settings.value.credentials.createdAt,
        },
        invitation: {
          allowIncomingInvitations: Boolean(settings.value.invitation.allowIncomingInvitations),
          allowOutgoingInvitations: Boolean(settings.value.invitation.allowOutgoingInvitations),
        },
        widget: settings.value.widget,
        realtimeBroadcast: { enabled: Boolean(settings.value.realtimeBroadcast.enabled) },
        favorites: settings.value.favorites,
        readLater: settings.value.readLater,
        friendApply: {
          enabled: Boolean(settings.value.friendApply.enabled),
          notifyEmail: String(settings.value.friendApply.notifyEmail || "").trim(),
          mailDriver: String(settings.value.friendApply.mailDriver || "phpmail"),
          smtpHost: String(settings.value.friendApply.smtpHost || "").trim(),
          smtpPort: Number(settings.value.friendApply.smtpPort || 587),
          smtpUser: String(settings.value.friendApply.smtpUser || "").trim(),
          smtpPass: String(settings.value.friendApply.smtpPass || ""),
          smtpSecure: String(settings.value.friendApply.smtpSecure || "tls"),
        },
      };
      const res = await apiPost("saveConfig", body);
      if (!res.ok) {
        throw new Error(res.message || "保存失败");
      }
      if (!options.silentSuccess) {
        Toast.success("保存成功");
      }
      return true;
    } catch {
      Toast.error("保存失败");
      return false;
    } finally {
      saving.value = false;
    }
  };

  return { loading, saving, settings, fetchSettings, saveSettings };
}
