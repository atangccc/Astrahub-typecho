export interface ConnectionSettings {
  hubBaseUrl: string;
  registerToken: string;
  siteName: string;
  siteUrl: string;
  siteDescription: string;
  contactEmail: string;
  siteNodeName: string;
  siteNodeAvatar: string;
  siteRssUrl: string;
}

export interface CredentialSettings {
  siteId: string;
  apiKey: string;
  createdAt: string;
  apiKeyMasked?: string;
  hasApiKey?: boolean;
}

export interface InvitationSettings {
  allowIncomingInvitations: boolean;
  allowOutgoingInvitations: boolean;
}

export interface WidgetSettings {
  enabled: boolean;
}

export interface RealtimeBroadcastSettings {
  enabled: boolean;
}

export interface FavoritesSettings {
  pinnedSiteUrls: string[];
}

export interface ReadLaterItem {
  url: string;
  title: string;
  summary: string;
  blogTitle: string;
  blogLogo: string;
  publishedAt: string;
  savedAt: string;
}

export interface ReadLaterSettings {
  items: ReadLaterItem[];
}

export interface FriendApplySettings {
  enabled: boolean;
  notifyEmail: string;
  mailDriver: string;
  smtpHost: string;
  smtpPort: number;
  smtpUser: string;
  smtpPass: string;
  smtpSecure: string;
}

export interface AstraHubSettings {
  connection: ConnectionSettings;
  credentials: CredentialSettings;
  invitation: InvitationSettings;
  widget: WidgetSettings;
  realtimeBroadcast: RealtimeBroadcastSettings;
  favorites: FavoritesSettings;
  readLater: ReadLaterSettings;
  friendApply: FriendApplySettings;
}

// 本地友链项（独立 astrahub_links 表）
export interface LocalLinkItem {
  flid: number;
  siteName: string;
  siteUrl: string;
  avatarUrl: string;
  summary: string;
  rssUrl: string;
  applicantEmail: string;
  targetSiteId: string;
  relationKind: string;
  groupId: number;
  reviewState: number; // 0=驳回/禁用 1=已通过 2=待审核
  sourceType: string; // admin | visitor
  applicantIp: string;
  sortOrder: number;
  createdAt: number;
  updatedAt: number;
}

export interface AstraHubRealtimeTokenResult {
  success: boolean;
  status: number;
  message: string;
  token?: string;
  expiresAt?: string;
}

export interface FriendInvitationSiteInfo {
  siteId: string;
  siteName: string;
  siteUrl: string;
  description?: string;
  avatarUrl?: string;
  rssUrl?: string;
}

export interface FriendInvitationItem {
  inviteId: string;
  fromSite: FriendInvitationSiteInfo;
  toSite: FriendInvitationSiteInfo;
  message?: string;
  status: string;
  deliveryStatus: string;
  reviewReason?: string;
  linkGroupName?: string;
  createdAt: string;
  reviewedAt?: string;
  ackedAt?: string;
  lastError?: string;
  retryCount: number;
  updatedAt: string;
}
