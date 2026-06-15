<?php
/**
 * 兜底定时：请求驱动的伪 cron。
 */

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

if (!class_exists('AstraHub_CredentialStore')) {
    require_once __DIR__ . '/CredentialStore.php';
}
if (!class_exists('AstraHub_PushService')) {
    require_once __DIR__ . '/PushService.php';
}

class AstraHub_Cron
{
    const OPTION_LAST = 'astrahub_cron_last';
    const INTERVAL = 86400;

    /**
     * @param bool $force
     * @return array{ran:bool,links?:array}
     */
    public static function tick($force = false)
    {
        $cred = \AstraHub_CredentialStore::credentials();
        if ($cred['siteId'] === '' || $cred['apiKey'] === '') {
            return array('ran' => false);
        }
        $last = (int) self::readLast();
        $now = time();
        if (!$force && $last > 0 && ($now - $last) < self::INTERVAL) {
            return array('ran' => false);
        }
        self::writeLast($now);

        $links = \AstraHub_PushService::pushLinkEdges();
        return array('ran' => true, 'links' => $links);
    }

    private static function readLast()
    {
        $db = \Typecho\Db::get();
        $prefix = $db->getPrefix();
        $row = $db->fetchRow(
            $db->select('value')->from($prefix . 'options')
                ->where('name = ?', self::OPTION_LAST)->where('user = ?', 0)->limit(1)
        );
        return $row ? (int) $row['value'] : 0;
    }

    private static function writeLast($ts)
    {
        $db = \Typecho\Db::get();
        $prefix = $db->getPrefix();
        $exists = $db->fetchRow(
            $db->select('name')->from($prefix . 'options')
                ->where('name = ?', self::OPTION_LAST)->where('user = ?', 0)->limit(1)
        );
        if ($exists) {
            $db->query($db->update($prefix . 'options')->rows(array('value' => (string) $ts))
                ->where('name = ?', self::OPTION_LAST)->where('user = ?', 0));
        } else {
            $db->query($db->insert($prefix . 'options')->rows(array(
                'name' => self::OPTION_LAST, 'user' => 0, 'value' => (string) $ts,
            )));
        }
    }
}
