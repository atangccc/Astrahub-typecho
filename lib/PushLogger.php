<?php
/**
 * 上报日志记录器。
 */

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class AstraHub_PushLogger
{
    const OPTION_NAME = 'astrahub_pushlog';
    const MAX_ENTRIES = 50;

    /**
     * @param string $kind
     * @param string $reason
     * @param array $items
     * @param array $result
     */
    public static function log($kind, $reason, array $items, array $result)
    {
        try {
            $entry = array(
                'time' => gmdate('Y-m-d\TH:i:s\Z'),
                'kind' => (string) $kind,
                'reason' => (string) $reason,
                'count' => count($items),
                'success' => isset($result['success']) ? (bool) $result['success'] : false,
                'status' => isset($result['status']) ? (int) $result['status'] : 0,
                'message' => isset($result['message']) ? (string) $result['message'] : '',
                'items' => array_slice($items, 0, 30),
            );

            $all = self::readAll();
            array_unshift($all, $entry);
            if (count($all) > self::MAX_ENTRIES) {
                $all = array_slice($all, 0, self::MAX_ENTRIES);
            }
            self::writeAll($all);
        } catch (\Exception $e) {
        }
    }

    public static function readAll()
    {
        $db = \Typecho\Db::get();
        $prefix = $db->getPrefix();
        try {
            $row = $db->fetchRow(
                $db->select('value')->from($prefix . 'options')
                    ->where('name = ?', self::OPTION_NAME)
                    ->where('user = ?', 0)
                    ->limit(1)
            );
        } catch (\Exception $e) {
            return array();
        }
        if (!$row || !isset($row['value']) || $row['value'] === '') {
            return array();
        }
        $decoded = json_decode($row['value'], true);
        return is_array($decoded) ? $decoded : array();
    }

    public static function clear()
    {
        self::writeAll(array());
        return true;
    }

    private static function writeAll(array $all)
    {
        $db = \Typecho\Db::get();
        $prefix = $db->getPrefix();
        $value = json_encode($all, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $exists = $db->fetchRow(
            $db->select('name')->from($prefix . 'options')
                ->where('name = ?', self::OPTION_NAME)
                ->where('user = ?', 0)
                ->limit(1)
        );
        if ($exists) {
            $db->query(
                $db->update($prefix . 'options')->rows(array('value' => $value))
                    ->where('name = ?', self::OPTION_NAME)
                    ->where('user = ?', 0)
            );
        } else {
            $db->query(
                $db->insert($prefix . 'options')->rows(array(
                    'name' => self::OPTION_NAME,
                    'user' => 0,
                    'value' => $value,
                ))
            );
        }
    }
}
