-- AstraHub for Typecho · MySQL / MariaDB 建表脚本
-- 安装时 Plugin.php 会用站点前缀替换 `typecho_`，并按分号切分逐条执行。
-- 字符集占位 %charset% 由 Plugin.php 替换为 utf8mb4。

-- 友链表：AstraHub 独立表，不与其它友链插件共用，字段全为我们自己的命名空间。
-- review_state: 0=驳回/禁用, 1=已通过(前台展示+上报Hub), 2=待审核
-- source_type: admin=后台添加, visitor=访客申请
CREATE TABLE IF NOT EXISTS `typecho_astrahub_links` (
  `flid` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `site_name` varchar(200) NOT NULL DEFAULT '',
  `site_url` varchar(512) NOT NULL DEFAULT '',
  `avatar_url` varchar(512) NOT NULL DEFAULT '',
  `summary` varchar(500) NOT NULL DEFAULT '',
  `rss_url` varchar(512) NOT NULL DEFAULT '',
  `applicant_email` varchar(150) NOT NULL DEFAULT '',
  `target_site_id` varchar(64) NOT NULL DEFAULT '',
  `relation_kind` varchar(32) NOT NULL DEFAULT 'none',
  `group_id` int(10) unsigned NOT NULL DEFAULT 0,
  `review_state` tinyint(4) NOT NULL DEFAULT 1,
  `source_type` varchar(16) NOT NULL DEFAULT 'admin',
  `applicant_ip` varchar(64) NOT NULL DEFAULT '',
  `sort_order` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` int(10) unsigned NOT NULL DEFAULT 0,
  `updated_at` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`flid`)
) ENGINE=InnoDB DEFAULT CHARSET=%charset%;

-- 友链分组表：本地友链分组。
-- 用于把本地友链归类，并在跨站友链「邀请 / 审核通过」时选择把对端归入哪个分组（linkGroupName）。
-- name 是分组机器名（=邀请协议里的 linkGroupName），display_name 是展示名。
CREATE TABLE IF NOT EXISTS `typecho_astrahub_link_groups` (
  `gid` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(120) NOT NULL DEFAULT '',
  `display_name` varchar(200) NOT NULL DEFAULT '',
  `sort_order` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` int(10) unsigned NOT NULL DEFAULT 0,
  `updated_at` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`gid`),
  UNIQUE KEY `uniq_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=%charset%;

-- 说明：邀请数据不落本地表，改为实时轮询 Hub inbox/outbox；
-- 去重 inviteId 集合、lastEventId 等轻状态存 Typecho options。
-- 设置/凭证同样存 options，不需要额外建表。
