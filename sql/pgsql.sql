-- AstraHub for Typecho · PostgreSQL 建表脚本
-- Plugin.php 会用站点前缀替换 typecho_，并按分号切分逐条执行。

-- 友链表：AstraHub 独立表，不与其它友链插件共用，字段全为我们自己的命名空间。
-- review_state: 0=驳回/禁用, 1=已通过(前台展示+上报Hub), 2=待审核
-- source_type: admin=后台添加, visitor=访客申请
CREATE TABLE IF NOT EXISTS "typecho_astrahub_links" (
  "flid" SERIAL PRIMARY KEY,
  "site_name" VARCHAR(200) NOT NULL DEFAULT '',
  "site_url" VARCHAR(512) NOT NULL DEFAULT '',
  "avatar_url" VARCHAR(512) NOT NULL DEFAULT '',
  "summary" VARCHAR(500) NOT NULL DEFAULT '',
  "rss_url" VARCHAR(512) NOT NULL DEFAULT '',
  "applicant_email" VARCHAR(150) NOT NULL DEFAULT '',
  "target_site_id" VARCHAR(64) NOT NULL DEFAULT '',
  "relation_kind" VARCHAR(32) NOT NULL DEFAULT 'none',
  "group_id" INTEGER NOT NULL DEFAULT 0,
  "review_state" SMALLINT NOT NULL DEFAULT 1,
  "source_type" VARCHAR(16) NOT NULL DEFAULT 'admin',
  "applicant_ip" VARCHAR(64) NOT NULL DEFAULT '',
  "sort_order" INTEGER NOT NULL DEFAULT 0,
  "created_at" INTEGER NOT NULL DEFAULT 0,
  "updated_at" INTEGER NOT NULL DEFAULT 0
);

-- 友链分组表：本地友链分组。
CREATE TABLE IF NOT EXISTS "typecho_astrahub_link_groups" (
  "gid" SERIAL PRIMARY KEY,
  "name" VARCHAR(120) NOT NULL DEFAULT '',
  "display_name" VARCHAR(200) NOT NULL DEFAULT '',
  "sort_order" INTEGER NOT NULL DEFAULT 0,
  "created_at" INTEGER NOT NULL DEFAULT 0,
  "updated_at" INTEGER NOT NULL DEFAULT 0,
  CONSTRAINT "uniq_astrahub_link_group_name" UNIQUE ("name")
);

-- 说明：邀请数据不落本地表，改为实时轮询 Hub；轻状态存 options。
