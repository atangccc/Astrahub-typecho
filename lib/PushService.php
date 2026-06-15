<?php
/**
 * 友链快照推送服务。
 */

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

if (!class_exists('AstraHub_HubClient')) {
    require_once __DIR__ . '/HubClient.php';
}
if (!class_exists('AstraHub_CredentialStore')) {
    require_once __DIR__ . '/CredentialStore.php';
}
if (!class_exists('AstraHub_LinksRepository')) {
    require_once __DIR__ . '/LinksRepository.php';
}
if (!class_exists('AstraHub_PushLogger')) {
    require_once __DIR__ . '/PushLogger.php';
}

class AstraHub_PushService
{
    const SCHEMA_VERSION = 'bp.site-links.v1';
    const PLUGIN_NAME = 'plugin-typecho-astrahub';
    const PLUGIN_VERSION = '0.1.0';

    /**
     * @return array{success:bool,status:int,message:string,delta:array,acceptedCount:int}
     */
    public static function pushLinkEdges()
    {
        $settings = \AstraHub_CredentialStore::load();
        $cred = \AstraHub_CredentialStore::credentials();
        if ($cred['siteId'] === '' || $cred['apiKey'] === '') {
            return self::fail('站点未接入，无法推送友链');
        }
        $base = self::hubBase($settings);
        $conn = isset($settings['connection']) ? $settings['connection'] : array();

        $snapshotAt = self::iso8601(time());
        $links = \AstraHub_LinksRepository::allApproved();
        $selfHost = self::hostOf(isset($conn['siteUrl']) ? (string) $conn['siteUrl'] : '');
        $edges = array();
        foreach ($links as $link) {
            if ($selfHost !== '' && self::hostOf($link['siteUrl']) === $selfHost) {
                continue;
            }
            $edges[] = array(
                'targetUrl' => $link['siteUrl'],
                'targetSiteId' => $link['targetSiteId'],
                'title' => $link['siteName'],
                'description' => $link['summary'],
                'logo' => $link['avatarUrl'],
                'rssUrl' => $link['rssUrl'],
                'isActive' => $link['isActive'] ? true : false,
                'firstSeenAt' => self::iso8601($link['createdAt'] > 0 ? $link['createdAt'] : time()),
                'lastSeenAt' => $snapshotAt,
                'updatedAt' => $snapshotAt,
            );
        }

        $payload = array(
            'version' => self::SCHEMA_VERSION,
            'snapshotAt' => $snapshotAt,
            'source' => array(
                'platform' => 'typecho',
                'plugin' => self::PLUGIN_NAME,
                'pluginVersion' => self::PLUGIN_VERSION,
                'siteId' => $cred['siteId'],
                'siteName' => isset($conn['siteName']) ? (string) $conn['siteName'] : '',
                'siteUrl' => isset($conn['siteUrl']) ? (string) $conn['siteUrl'] : '',
            ),
            'edges' => $edges,
        );

        $client = new \AstraHub_HubClient($base, 20);
        $resp = $client->signedRequest('POST', '/v1/site-link-edges/push', $payload, $cred['siteId'], $cred['apiKey']);
        $json = is_array($resp['json']) ? $resp['json'] : array();
        $ok = $resp['ok'] && !empty($json['accepted']);
        $result = array(
            'success' => $ok,
            'status' => $resp['status'],
            'message' => $ok ? 'ok' : self::hubMessage($json, $resp),
            'delta' => isset($json['delta']) && is_array($json['delta']) ? $json['delta'] : array(),
            'acceptedCount' => isset($json['acceptedCount']) ? (int) $json['acceptedCount'] : 0,
            'edgesCount' => count($edges),
        );

        $logItems = array();
        foreach ($edges as $e) {
            $logItems[] = array(
                'title' => isset($e['title']) ? $e['title'] : '',
                'url' => isset($e['targetUrl']) ? $e['targetUrl'] : '',
            );
        }
        \AstraHub_PushLogger::log('links', 'manual', $logItems, $result);

        return $result;
    }

    private static function hostOf($url)
    {
        $url = trim((string) $url);
        if ($url === '') {
            return '';
        }
        if (!preg_match('#^[a-z][a-z0-9+.\-]*://#i', $url)) {
            $url = 'http://' . $url;
        }
        $host = parse_url($url, PHP_URL_HOST);
        return is_string($host) ? strtolower($host) : '';
    }

    private static function hubBase($settings)
    {
        $base = isset($settings['connection']['hubBaseUrl']) ? trim((string) $settings['connection']['hubBaseUrl']) : '';
        return $base !== '' ? $base : 'https://astra.aobp.cn';
    }

    private static function iso8601($ts)
    {
        $ts = (int) $ts;
        if ($ts <= 0) {
            $ts = time();
        }
        return gmdate('Y-m-d\TH:i:s\Z', $ts);
    }

    private static function hubMessage($json, $resp)
    {
        if (isset($json['error']) && is_array($json['error']) && isset($json['error']['message'])) {
            return (string) $json['error']['message'];
        }
        if (isset($json['message'])) {
            return (string) $json['message'];
        }
        if ($resp['error'] !== '') {
            return $resp['error'];
        }
        return '推送失败：HTTP ' . $resp['status'];
    }

    private static function fail($message)
    {
        return array('success' => false, 'status' => 0, 'message' => $message, 'delta' => array(), 'acceptedCount' => 0, 'edgesCount' => 0);
    }
}
