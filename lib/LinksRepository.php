<?php
/**
 * AstraHub 友链存储库（typecho_astrahub_links 表）。
 *
 * review_state: 0=驳回, 1=已通过, 2=待审核
 * source_type: admin=后台添加, visitor=访客申请
 */

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class AstraHub_LinksRepository
{
    private static $schemaEnsured = false;

    private static function db()
    {
        return \Typecho\Db::get();
    }

    private static function table()
    {
        return self::db()->getPrefix() . 'astrahub_links';
    }

    private static function ensureSchema()
    {
        if (self::$schemaEnsured) {
            return;
        }
        self::$schemaEnsured = true;

        $db = self::db();
        $adapter = $db->getAdapterName();
        if (strpos($adapter, 'Mysql') === false && strpos($adapter, 'Mysqli') === false) {
            return;
        }
        $table = self::table();

        try {
            $rows = $db->fetchAll($db->query("SHOW COLUMNS FROM `{$table}`", \Typecho\Db::READ));
        } catch (\Exception $e) {
            return;
        }
        $existing = array();
        foreach ($rows as $row) {
            if (isset($row['Field'])) {
                $existing[strtolower($row['Field'])] = true;
            }
        }

        $columns = array(
            'avatar_url' => "varchar(512) NOT NULL DEFAULT ''",
            'summary' => "varchar(500) NOT NULL DEFAULT ''",
            'rss_url' => "varchar(512) NOT NULL DEFAULT ''",
            'applicant_email' => "varchar(150) NOT NULL DEFAULT ''",
            'target_site_id' => "varchar(64) NOT NULL DEFAULT ''",
            'relation_kind' => "varchar(32) NOT NULL DEFAULT 'none'",
            'group_id' => "int(10) unsigned NOT NULL DEFAULT 0",
            'review_state' => "tinyint(4) NOT NULL DEFAULT 1",
            'source_type' => "varchar(16) NOT NULL DEFAULT 'admin'",
            'applicant_ip' => "varchar(64) NOT NULL DEFAULT ''",
            'sort_order' => "int(10) unsigned NOT NULL DEFAULT 0",
            'created_at' => "int(10) unsigned NOT NULL DEFAULT 0",
            'updated_at' => "int(10) unsigned NOT NULL DEFAULT 0",
        );

        foreach ($columns as $col => $def) {
            if (!isset($existing[$col])) {
                try {
                    $db->query("ALTER TABLE `{$table}` ADD COLUMN `{$col}` {$def}", \Typecho\Db::WRITE);
                } catch (\Exception $e) {
                    continue;
                }
            }
        }
    }

    public static function all($reviewState = null)
    {
        $db = self::db();
        $query = $db->select()->from(self::table())->order('sort_order', \Typecho\Db::SORT_ASC);
        if ($reviewState !== null) {
            $query->where('review_state = ?', (int) $reviewState);
        }
        $rows = $db->fetchAll($query);
        return array_map(array(__CLASS__, 'normalizeRow'), $rows);
    }

    public static function allApproved()
    {
        return self::all(1);
    }

    public static function allPending()
    {
        return self::all(2);
    }

    public static function get($flid)
    {
        $db = self::db();
        $row = $db->fetchRow(
            $db->select()->from(self::table())->where('flid = ?', (int) $flid)->limit(1)
        );
        return $row ? self::normalizeRow($row) : null;
    }

    public static function findByUrl($url)
    {
        $target = self::normalizeUrl($url);
        if ($target === '') {
            return null;
        }
        foreach (self::all() as $link) {
            if (self::normalizeUrl($link['siteUrl']) === $target) {
                return $link;
            }
        }
        return null;
    }

    public static function insert(array $data)
    {
        self::ensureSchema();
        $db = self::db();
        $now = time();

        $maxRow = $db->fetchRow(
            $db->select('sort_order')->from(self::table())
                ->order('sort_order', \Typecho\Db::SORT_DESC)
                ->limit(1)
        );
        $maxOrder = $maxRow ? (int) $maxRow['sort_order'] : 0;

        $row = self::toDbRow($data);
        $row['sort_order'] = $maxOrder + 1;
        $row['source_type'] = isset($data['source_type']) ? (string) $data['source_type'] : 'admin';
        $row['review_state'] = 1;
        if (empty($row['created_at'])) {
            $row['created_at'] = $now;
        }
        $row['updated_at'] = $now;

        return $db->query($db->insert(self::table())->rows($row));
    }

    public static function applyFromVisitor(array $data, $ip = '')
    {
        self::ensureSchema();
        $db = self::db();
        $now = time();

        $maxRow = $db->fetchRow(
            $db->select('sort_order')->from(self::table())
                ->order('sort_order', \Typecho\Db::SORT_DESC)
                ->limit(1)
        );
        $maxOrder = $maxRow ? (int) $maxRow['sort_order'] : 0;

        $row = self::toDbRow($data);
        $row['sort_order'] = $maxOrder + 1;
        $row['source_type'] = 'visitor';
        $row['review_state'] = 2;
        $row['applicant_ip'] = trim((string) $ip);
        if (empty($row['created_at'])) {
            $row['created_at'] = $now;
        }
        $row['updated_at'] = $now;

        return $db->query($db->insert(self::table())->rows($row));
    }

    public static function update($flid, array $data)
    {
        self::ensureSchema();
        $db = self::db();
        $now = time();
        $flid = (int) $flid;

        $row = self::toDbRow($data);
        $row['updated_at'] = $now;

        return $db->query(
            $db->update(self::table())->rows($row)->where('flid = ?', $flid)
        );
    }

    public static function approve($flid)
    {
        return self::update($flid, array('reviewState' => 1));
    }

    public static function reject($flid)
    {
        return self::update($flid, array('reviewState' => 0));
    }

    public static function enable($flid)
    {
        return self::update($flid, array('reviewState' => 1));
    }

    public static function disable($flid)
    {
        return self::update($flid, array('reviewState' => 0));
    }

    public static function delete($flid)
    {
        $db = self::db();
        return $db->query($db->delete(self::table())->where('flid = ?', (int) $flid));
    }

    public static function reorder(array $flids)
    {
        $db = self::db();
        foreach ($flids as $idx => $flid) {
            $db->query(
                $db->update(self::table())->rows(array('sort_order' => $idx + 1))->where('flid = ?', (int) $flid)
            );
        }
        return true;
    }

    public static function count($reviewState = null)
    {
        $db = self::db();
        $query = $db->select(array('COUNT(*)' => 'num'))->from(self::table());
        if ($reviewState !== null) {
            $query->where('review_state = ?', (int) $reviewState);
        }
        $obj = $db->fetchObject($query);
        return (int) $obj->num;
    }

    private static function toDbRow(array $data)
    {
        $row = array();

        $fieldMap = array(
            'site_name' => array('siteName', 'site_name', 'name'),
            'site_url' => array('siteUrl', 'site_url', 'url'),
            'avatar_url' => array('avatarUrl', 'avatar_url', 'image'),
            'summary' => array('summary', 'description'),
            'rss_url' => array('rssUrl', 'rss_url'),
            'applicant_email' => array('applicantEmail', 'applicant_email', 'email'),
            'target_site_id' => array('targetSiteId', 'target_site_id'),
            'relation_kind' => array('relationKind', 'relation_kind'),
            'group_id' => array('groupId', 'group_id'),
            'review_state' => array('reviewState', 'review_state', 'state'),
            'source_type' => array('sourceType', 'source_type'),
            'applicant_ip' => array('applicantIp', 'applicant_ip'),
            'sort_order' => array('sortOrder', 'sort_order', 'order'),
            'created_at' => array('createdAt', 'created_at', 'firstSeenAt'),
            'updated_at' => array('updatedAt', 'updated_at', 'lastSeenAt'),
        );

        foreach ($fieldMap as $dbField => $inputKeys) {
            foreach ($inputKeys as $inputKey) {
                if (array_key_exists($inputKey, $data)) {
                    $value = $data[$inputKey];
                    if ($dbField === 'review_state' && is_bool($value)) {
                        $value = $value ? 1 : 0;
                    }
                    if ($inputKey === 'isActive') {
                        $value = $value ? 1 : 0;
                    }
                    $row[$dbField] = is_string($value) ? trim($value) : $value;
                    break;
                }
            }
        }

        if (array_key_exists('isActive', $data) && !isset($row['review_state'])) {
            $row['review_state'] = $data['isActive'] ? 1 : 0;
        }

        return $row;
    }

    private static function normalizeRow($row)
    {
        $reviewState = (int) (isset($row['review_state']) ? $row['review_state'] : 1);
        return array(
            'flid' => (int) $row['flid'],
            'lid' => (int) $row['flid'],
            'siteName' => (string) (isset($row['site_name']) ? $row['site_name'] : ''),
            'name' => (string) (isset($row['site_name']) ? $row['site_name'] : ''),
            'siteUrl' => (string) (isset($row['site_url']) ? $row['site_url'] : ''),
            'url' => (string) (isset($row['site_url']) ? $row['site_url'] : ''),
            'avatarUrl' => (string) (isset($row['avatar_url']) ? $row['avatar_url'] : ''),
            'image' => (string) (isset($row['avatar_url']) ? $row['avatar_url'] : ''),
            'summary' => (string) (isset($row['summary']) ? $row['summary'] : ''),
            'description' => (string) (isset($row['summary']) ? $row['summary'] : ''),
            'rssUrl' => (string) (isset($row['rss_url']) ? $row['rss_url'] : ''),
            'applicantEmail' => (string) (isset($row['applicant_email']) ? $row['applicant_email'] : ''),
            'email' => (string) (isset($row['applicant_email']) ? $row['applicant_email'] : ''),
            'targetSiteId' => (string) (isset($row['target_site_id']) ? $row['target_site_id'] : ''),
            'relationKind' => (string) (isset($row['relation_kind']) && $row['relation_kind'] !== '' ? $row['relation_kind'] : 'none'),
            'groupId' => (int) (isset($row['group_id']) ? $row['group_id'] : 0),
            'reviewState' => $reviewState,
            'isActive' => $reviewState === 1,
            'sourceType' => (string) (isset($row['source_type']) ? $row['source_type'] : 'admin'),
            'applicantIp' => (string) (isset($row['applicant_ip']) ? $row['applicant_ip'] : ''),
            'sortOrder' => (int) (isset($row['sort_order']) ? $row['sort_order'] : 0),
            'order' => (int) (isset($row['sort_order']) ? $row['sort_order'] : 0),
            'createdAt' => (int) (isset($row['created_at']) ? $row['created_at'] : 0),
            'updatedAt' => (int) (isset($row['updated_at']) ? $row['updated_at'] : 0),
            'firstSeenAt' => (int) (isset($row['created_at']) ? $row['created_at'] : 0),
            'lastSeenAt' => (int) (isset($row['updated_at']) ? $row['updated_at'] : 0),
        );
    }

    private static function normalizeUrl($url)
    {
        $u = strtolower(trim((string) $url));
        $u = preg_replace('#^https?://#', '', $u);
        $u = rtrim($u, '/');
        return $u;
    }
}
