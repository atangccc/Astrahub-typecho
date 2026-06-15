<?php
/**
 * AstraHub 友链分组存储库。
 */

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class AstraHub_LinkGroupsRepository
{
    private static function db()
    {
        return \Typecho\Db::get();
    }

    private static function table()
    {
        return self::db()->getPrefix() . 'astrahub_link_groups';
    }

    private static $ensured = false;

    private static function ensureTable()
    {
        if (self::$ensured) {
            return;
        }
        self::$ensured = true;

        $db = self::db();
        $table = self::table();
        $adapter = $db->getAdapterName();

        try {
            if (strpos($adapter, 'SQLite') !== false) {
                $sql = "CREATE TABLE IF NOT EXISTS `{$table}` ("
                    . "`gid` INTEGER PRIMARY KEY AUTOINCREMENT,"
                    . "`name` varchar(120) NOT NULL DEFAULT '',"
                    . "`display_name` varchar(200) NOT NULL DEFAULT '',"
                    . "`sort_order` int NOT NULL DEFAULT 0,"
                    . "`created_at` int NOT NULL DEFAULT 0,"
                    . "`updated_at` int NOT NULL DEFAULT 0)";
                $db->query($sql, \Typecho\Db::WRITE);
                $db->query(
                    "CREATE UNIQUE INDEX IF NOT EXISTS `{$table}_uniq_name` ON `{$table}` (`name`)",
                    \Typecho\Db::WRITE
                );
            } elseif (strpos($adapter, 'Pgsql') !== false) {
                $sql = "CREATE TABLE IF NOT EXISTS \"{$table}\" ("
                    . "\"gid\" SERIAL PRIMARY KEY,"
                    . "\"name\" varchar(120) NOT NULL DEFAULT '',"
                    . "\"display_name\" varchar(200) NOT NULL DEFAULT '',"
                    . "\"sort_order\" integer NOT NULL DEFAULT 0,"
                    . "\"created_at\" integer NOT NULL DEFAULT 0,"
                    . "\"updated_at\" integer NOT NULL DEFAULT 0,"
                    . "CONSTRAINT \"{$table}_uniq_name\" UNIQUE (\"name\"))";
                $db->query($sql, \Typecho\Db::WRITE);
            } else {
                $sql = "CREATE TABLE IF NOT EXISTS `{$table}` ("
                    . "`gid` int(10) unsigned NOT NULL AUTO_INCREMENT,"
                    . "`name` varchar(120) NOT NULL DEFAULT '',"
                    . "`display_name` varchar(200) NOT NULL DEFAULT '',"
                    . "`sort_order` int(10) unsigned NOT NULL DEFAULT 0,"
                    . "`created_at` int(10) unsigned NOT NULL DEFAULT 0,"
                    . "`updated_at` int(10) unsigned NOT NULL DEFAULT 0,"
                    . "PRIMARY KEY (`gid`),"
                    . "UNIQUE KEY `uniq_name` (`name`)"
                    . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
                $db->query($sql, \Typecho\Db::WRITE);
            }
        } catch (\Exception $e) {
        }
    }

    public static function all()
    {
        self::ensureTable();
        $db = self::db();
        $rows = $db->fetchAll(
            $db->select()->from(self::table())->order('sort_order', \Typecho\Db::SORT_ASC)
        );
        return array_map(array(__CLASS__, 'normalizeRow'), $rows);
    }

    public static function get($gid)
    {
        self::ensureTable();
        $db = self::db();
        $row = $db->fetchRow(
            $db->select()->from(self::table())->where('gid = ?', (int) $gid)->limit(1)
        );
        return $row ? self::normalizeRow($row) : null;
    }

    public static function findByName($name)
    {
        self::ensureTable();
        $name = trim((string) $name);
        if ($name === '') {
            return null;
        }
        $db = self::db();
        $row = $db->fetchRow(
            $db->select()->from(self::table())->where('name = ?', $name)->limit(1)
        );
        return $row ? self::normalizeRow($row) : null;
    }

    /**
     * @param array $data
     */
    public static function insert(array $data)
    {
        self::ensureTable();
        $db = self::db();
        $now = time();

        $displayName = isset($data['displayName']) ? trim((string) $data['displayName']) : '';
        if ($displayName === '' && isset($data['display_name'])) {
            $displayName = trim((string) $data['display_name']);
        }
        $name = isset($data['name']) ? self::slug($data['name']) : '';
        if ($name === '') {
            $name = self::slug($displayName);
        }
        if ($name === '') {
            $name = 'group-' . $now;
        }

        $maxRow = $db->fetchRow(
            $db->select('sort_order')->from(self::table())
                ->order('sort_order', \Typecho\Db::SORT_DESC)
                ->limit(1)
        );
        $maxOrder = $maxRow ? (int) $maxRow['sort_order'] : 0;

        return $db->query($db->insert(self::table())->rows(array(
            'name' => $name,
            'display_name' => $displayName !== '' ? $displayName : $name,
            'sort_order' => $maxOrder + 1,
            'created_at' => $now,
            'updated_at' => $now,
        )));
    }

    public static function update($gid, array $data)
    {
        self::ensureTable();
        $db = self::db();
        $row = array('updated_at' => time());
        if (isset($data['displayName'])) {
            $row['display_name'] = trim((string) $data['displayName']);
        } elseif (isset($data['display_name'])) {
            $row['display_name'] = trim((string) $data['display_name']);
        }
        if (isset($data['sortOrder'])) {
            $row['sort_order'] = (int) $data['sortOrder'];
        } elseif (isset($data['sort_order'])) {
            $row['sort_order'] = (int) $data['sort_order'];
        }
        return $db->query(
            $db->update(self::table())->rows($row)->where('gid = ?', (int) $gid)
        );
    }

    public static function delete($gid)
    {
        $db = self::db();
        $gid = (int) $gid;
        $linksTable = $db->getPrefix() . 'astrahub_links';
        $db->query(
            $db->update($linksTable)->rows(array('group_id' => 0))->where('group_id = ?', $gid)
        );
        return $db->query($db->delete(self::table())->where('gid = ?', $gid));
    }

    public static function reorder(array $gids)
    {
        $db = self::db();
        foreach ($gids as $idx => $gid) {
            $db->query(
                $db->update(self::table())->rows(array('sort_order' => $idx + 1))->where('gid = ?', (int) $gid)
            );
        }
        return true;
    }

    /**
     * @return array<int,array{name:string,displayName:string}>
     */
    public static function options()
    {
        $items = array();
        foreach (self::all() as $g) {
            $items[] = array('name' => $g['name'], 'displayName' => $g['displayName']);
        }
        return $items;
    }

    private static function normalizeRow($row)
    {
        return array(
            'gid' => (int) $row['gid'],
            'name' => (string) (isset($row['name']) ? $row['name'] : ''),
            'displayName' => (string) (isset($row['display_name']) ? $row['display_name'] : ''),
            'sortOrder' => (int) (isset($row['sort_order']) ? $row['sort_order'] : 0),
            'createdAt' => (int) (isset($row['created_at']) ? $row['created_at'] : 0),
            'updatedAt' => (int) (isset($row['updated_at']) ? $row['updated_at'] : 0),
        );
    }

    private static function slug($raw)
    {
        $value = trim((string) $raw);
        if ($value === '') {
            return '';
        }
        $value = preg_replace('/\s+/u', '-', $value);
        $value = preg_replace('#[/\\\\?#%]+#', '', $value);
        $value = trim($value, '-');
        if (function_exists('mb_strtolower')) {
            $value = mb_strtolower($value, 'UTF-8');
        } else {
            $value = strtolower($value);
        }
        return $value;
    }
}
