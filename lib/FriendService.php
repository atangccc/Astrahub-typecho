<?php
/**
 * 友链邀请协议服务。
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
if (!class_exists('AstraHub_LinkGroupsRepository')) {
    require_once __DIR__ . '/LinkGroupsRepository.php';
}

class AstraHub_FriendService
{
    private static function ctx()
    {
        $cred = \AstraHub_CredentialStore::credentials();
        if ($cred['siteId'] === '' || $cred['apiKey'] === '') {
            return null;
        }
        $s = \AstraHub_CredentialStore::load();
        $base = isset($s['connection']['hubBaseUrl']) && $s['connection']['hubBaseUrl'] !== ''
            ? $s['connection']['hubBaseUrl'] : 'https://astra.aobp.cn';
        return array('siteId' => $cred['siteId'], 'apiKey' => $cred['apiKey'], 'base' => $base);
    }

    public static function list($box, $status = '')
    {
        $ctx = self::ctx();
        if (!$ctx) {
            return self::fail('站点未接入');
        }
        $box = ($box === 'outbox') ? 'outbox' : 'inbox';
        $path = '/v1/friend-invitations/' . $box;
        $params = array();
        if ($status !== '') {
            $params['status'] = $status;
        }
        if (!empty($params)) {
            $path .= '?' . http_build_query($params);
        }
        $resp = self::client($ctx)->signedRequest('GET', $path, null, $ctx['siteId'], $ctx['apiKey']);
        $json = is_array($resp['json']) ? $resp['json'] : array();
        if (!$resp['ok']) {
            return self::fail(self::msg($json, $resp), $resp['status']);
        }
        return array(
            'success' => true,
            'generatedAt' => isset($json['generatedAt']) ? (string) $json['generatedAt'] : '',
            'total' => isset($json['total']) ? (int) $json['total'] : 0,
            'items' => isset($json['items']) && is_array($json['items']) ? $json['items'] : array(),
        );
    }

    public static function create($toSiteId, $message = '', $linkGroupName = '')
    {
        $ctx = self::ctx();
        if (!$ctx) {
            return self::fail('站点未接入');
        }
        $idempotencyKey = self::buildIdempotencyKey($ctx['siteId'], (string) $toSiteId, (string) $message);
        $payload = array(
            'toSiteId' => (string) $toSiteId,
            'message' => (string) $message,
            'linkGroupName' => (string) $linkGroupName,
            'idempotencyKey' => $idempotencyKey,
        );
        $resp = self::client($ctx)->signedRequest(
            'POST',
            '/v1/friend-invitations',
            $payload,
            $ctx['siteId'],
            $ctx['apiKey'],
            array('X-BP-Idempotency-Key' => $idempotencyKey)
        );
        return self::invitationResult($resp);
    }

    private static function buildIdempotencyKey($fromSiteId, $toSiteId, $message)
    {
        $payload = trim((string) $fromSiteId) . "\n" . trim((string) $toSiteId) . "\n" . trim((string) $message);
        $hash = hash('sha256', $payload, true);
        return 'fi_' . bin2hex(substr($hash, 0, 16));
    }

    public static function review($inviteId, $approved, $reason = '', $linkGroupName = '')
    {
        $ctx = self::ctx();
        if (!$ctx) {
            return self::fail('站点未接入');
        }
        $payload = array(
            'approved' => (bool) $approved,
            'reason' => (string) $reason,
            'linkGroupName' => (string) $linkGroupName,
        );
        $path = '/v1/friend-invitations/' . rawurlencode($inviteId) . '/review';
        $resp = self::client($ctx)->signedRequest('POST', $path, $payload, $ctx['siteId'], $ctx['apiKey']);
        return self::invitationResult($resp);
    }

    public static function ack($inviteId, $lastError = '')
    {
        $ctx = self::ctx();
        if (!$ctx) {
            return self::fail('站点未接入');
        }
        $path = '/v1/friend-invitations/' . rawurlencode($inviteId) . '/ack';
        $resp = self::client($ctx)->signedRequest('POST', $path, array('lastError' => (string) $lastError), $ctx['siteId'], $ctx['apiKey']);
        return self::simpleResult($resp);
    }

    public static function cancel($inviteId)
    {
        $ctx = self::ctx();
        if (!$ctx) {
            return self::fail('站点未接入');
        }
        $path = '/v1/friend-invitations/' . rawurlencode($inviteId) . '/cancel';
        $resp = self::client($ctx)->signedRequest('POST', $path, null, $ctx['siteId'], $ctx['apiKey']);
        return self::invitationResult($resp);
    }

    public static function delete($inviteId)
    {
        $ctx = self::ctx();
        if (!$ctx) {
            return self::fail('站点未接入');
        }
        $path = '/v1/friend-invitations/' . rawurlencode($inviteId) . '/delete';
        $resp = self::client($ctx)->signedRequest('POST', $path, null, $ctx['siteId'], $ctx['apiKey']);
        return self::simpleResult($resp);
    }

    public static function removeRelation($peerSiteId, $reason = '')
    {
        $ctx = self::ctx();
        if (!$ctx) {
            return self::fail('站点未接入');
        }
        $peerSiteId = trim((string) $peerSiteId);
        if ($peerSiteId === '') {
            return self::fail('缺少对端站点编号');
        }
        $path = '/v1/friend-relations/' . rawurlencode($peerSiteId) . '/remove';
        $payload = ($reason !== '') ? array('reason' => (string) $reason) : null;
        $resp = self::client($ctx)->signedRequest('POST', $path, $payload, $ctx['siteId'], $ctx['apiKey']);
        $json = is_array($resp['json']) ? $resp['json'] : array();
        $ok = $resp['ok'] && (!isset($json['success']) || $json['success']);
        if ($ok) {
            $peerUrl = isset($json['peerSiteUrl']) ? (string) $json['peerSiteUrl'] : '';
            self::deleteLocalLink($peerSiteId, $peerUrl);
        }
        return array(
            'success' => $ok,
            'status' => $resp['status'],
            'message' => $ok ? 'ok' : self::msg($json, $resp),
            'removed' => isset($json['removed']) ? (bool) $json['removed'] : false,
            'forwardRemoved' => isset($json['forwardRemoved']) ? (bool) $json['forwardRemoved'] : false,
            'reverseRemoved' => isset($json['reverseRemoved']) ? (bool) $json['reverseRemoved'] : false,
            'peerSiteId' => isset($json['peerSiteId']) ? (string) $json['peerSiteId'] : $peerSiteId,
            'peerSiteUrl' => isset($json['peerSiteUrl']) ? (string) $json['peerSiteUrl'] : '',
        );
    }

    public static function removeFollow($peerSiteId)
    {
        $ctx = self::ctx();
        if (!$ctx) {
            return self::fail('站点未接入');
        }
        $peerSiteId = trim((string) $peerSiteId);
        if ($peerSiteId === '') {
            return self::fail('缺少对端站点编号');
        }
        $path = '/v1/friend-follows/' . rawurlencode($peerSiteId) . '/remove';
        $resp = self::client($ctx)->signedRequest('POST', $path, null, $ctx['siteId'], $ctx['apiKey']);
        $json = is_array($resp['json']) ? $resp['json'] : array();
        $ok = $resp['ok'] && (!isset($json['success']) || $json['success']);
        if ($ok) {
            $peerUrl = isset($json['peerSiteUrl']) ? (string) $json['peerSiteUrl'] : '';
            self::deleteLocalLink($peerSiteId, $peerUrl);
        }
        return array(
            'success' => $ok,
            'status' => $resp['status'],
            'message' => $ok ? 'ok' : self::msg($json, $resp),
            'removed' => isset($json['removed']) ? (bool) $json['removed'] : false,
            'peerSiteId' => isset($json['peerSiteId']) ? (string) $json['peerSiteId'] : $peerSiteId,
            'peerSiteUrl' => isset($json['peerSiteUrl']) ? (string) $json['peerSiteUrl'] : '',
        );
    }

    private static function deleteLocalLink($peerSiteId, $peerUrl)
    {
        $peerSiteId = trim((string) $peerSiteId);
        $targetUrl = self::normalizeUrl($peerUrl);
        foreach (\AstraHub_LinksRepository::all() as $link) {
            $matchById = $peerSiteId !== '' && (string) $link['targetSiteId'] === $peerSiteId;
            $matchByUrl = $targetUrl !== '' && self::normalizeUrl($link['url']) === $targetUrl;
            if ($matchById || $matchByUrl) {
                \AstraHub_LinksRepository::delete($link['lid']);
            }
        }
    }

    public static function deleteLocalLinkByPeer($peerSiteId, $peerUrl)
    {
        $peerSiteId = trim((string) $peerSiteId);
        $targetUrl = self::normalizeUrl($peerUrl);
        if ($peerSiteId === '' && $targetUrl === '') {
            return array('success' => false, 'deleted' => 0);
        }
        $deleted = 0;
        foreach (\AstraHub_LinksRepository::all() as $link) {
            $matchById = $peerSiteId !== '' && (string) $link['targetSiteId'] === $peerSiteId;
            $matchByUrl = $targetUrl !== '' && self::normalizeUrl($link['url']) === $targetUrl;
            if ($matchById || $matchByUrl) {
                \AstraHub_LinksRepository::delete($link['flid']);
                $deleted++;
            }
        }
        return array('success' => true, 'deleted' => $deleted);
    }

    public static function updateLocalLinkByPeerSiteId($peerSiteId, array $profile)
    {
        $peerSiteId = trim((string) $peerSiteId);
        if ($peerSiteId === '') {
            return array('success' => false, 'updated' => 0, 'message' => 'peerSiteId is required');
        }

        $name = isset($profile['name']) ? trim((string) $profile['name']) : '';
        $url = isset($profile['url']) ? trim((string) $profile['url']) : '';
        $description = isset($profile['description']) ? trim((string) $profile['description']) : '';
        $avatarUrl = isset($profile['avatarUrl']) ? trim((string) $profile['avatarUrl']) : '';
        $rssUrl = isset($profile['rssUrl']) ? trim((string) $profile['rssUrl']) : '';

        $targetUrl = self::normalizeUrl($url);
        $matchedById = array();
        $matchedByUrl = array();
        foreach (\AstraHub_LinksRepository::all() as $link) {
            if ($peerSiteId !== '' && (string) $link['targetSiteId'] === $peerSiteId) {
                $matchedById[] = $link;
            } elseif ($targetUrl !== '' && self::normalizeUrl($link['url']) === $targetUrl) {
                $matchedByUrl[] = $link;
            }
        }
        $targets = !empty($matchedById) ? $matchedById : $matchedByUrl;
        if (empty($targets)) {
            return array('success' => true, 'updated' => 0, 'message' => 'not_found');
        }

        $count = 0;
        foreach ($targets as $link) {
            $patch = array(
                'relation_kind' => $link['relationKind'],
                'target_site_id' => $peerSiteId,
            );
            if ($name !== '') {
                $patch['site_name'] = $name;
            }
            if ($url !== '') {
                $patch['site_url'] = $url;
            }
            $patch['summary'] = $description;
            $patch['avatar_url'] = $avatarUrl;
            $patch['rss_url'] = $rssUrl;
            \AstraHub_LinksRepository::update($link['flid'], $patch);
            $count++;
        }
        return array('success' => true, 'updated' => $count, 'message' => 'updated');
    }

    public static function reconcileLocalLink(array $peer, $relationKind = 'mutual', $linkGroupName = '', $sourceType = 'invitation')
    {
        $url = isset($peer['siteUrl']) ? trim((string) $peer['siteUrl']) : '';
        $name = isset($peer['siteName']) ? trim((string) $peer['siteName']) : '';
        if ($url === '' || $name === '') {
            return array('success' => false, 'created' => false, 'duplicate' => false, 'message' => '对端站点信息不完整');
        }
        $groupId = 0;
        $linkGroupName = trim((string) $linkGroupName);
        if ($linkGroupName !== '') {
            $group = \AstraHub_LinkGroupsRepository::findByName($linkGroupName);
            if ($group) {
                $groupId = (int) $group['gid'];
            }
        }
        $existing = self::findLinkByUrl($url);
        if ($existing) {
            $patch = array(
                'target_site_id' => isset($peer['siteId']) ? (string) $peer['siteId'] : $existing['targetSiteId'],
                'relation_kind' => $relationKind,
                'site_name' => $name,
                'avatar_url' => isset($peer['avatarUrl']) ? (string) $peer['avatarUrl'] : '',
                'summary' => isset($peer['description']) ? (string) $peer['description'] : '',
                'rss_url' => isset($peer['rssUrl']) ? (string) $peer['rssUrl'] : '',
            );
            if ($groupId > 0) {
                $patch['groupId'] = $groupId;
            }
            \AstraHub_LinksRepository::update($existing['lid'], $patch);
            return array('success' => true, 'created' => false, 'duplicate' => true, 'message' => '友链已存在，已覆盖为对端最新信息');
        }
        \AstraHub_LinksRepository::insert(array(
            'name' => $name,
            'url' => $url,
            'image' => isset($peer['avatarUrl']) ? (string) $peer['avatarUrl'] : '',
            'description' => isset($peer['description']) ? (string) $peer['description'] : '',
            'rss_url' => isset($peer['rssUrl']) ? (string) $peer['rssUrl'] : '',
            'target_site_id' => isset($peer['siteId']) ? (string) $peer['siteId'] : '',
            'relation_kind' => $relationKind,
            'group_id' => $groupId,
            'source_type' => $sourceType,
            'is_active' => true,
        ));
        return array('success' => true, 'created' => true, 'duplicate' => false, 'message' => '已加入本地友链');
    }

    private static function findLinkByUrl($url)
    {
        $target = self::normalizeUrl($url);
        foreach (\AstraHub_LinksRepository::all() as $link) {
            if (self::normalizeUrl($link['url']) === $target) {
                return $link;
            }
        }
        return null;
    }

    private static function normalizeUrl($url)
    {
        $u = strtolower(trim((string) $url));
        $u = preg_replace('#^https?://#', '', $u);
        $u = rtrim($u, '/');
        return $u;
    }

    private static function client($ctx)
    {
        return new \AstraHub_HubClient($ctx['base'], 20);
    }

    private static function invitationResult($resp)
    {
        $json = is_array($resp['json']) ? $resp['json'] : array();
        $ok = $resp['ok'] && (!isset($json['success']) || $json['success']);
        return array(
            'success' => $ok,
            'status' => $resp['status'],
            'message' => $ok ? 'ok' : self::msg($json, $resp),
            'invitation' => isset($json['invitation']) ? $json['invitation'] : null,
        );
    }

    private static function simpleResult($resp)
    {
        $json = is_array($resp['json']) ? $resp['json'] : array();
        $ok = $resp['ok'] && (!isset($json['success']) || $json['success']);
        return array('success' => $ok, 'status' => $resp['status'], 'message' => $ok ? 'ok' : self::msg($json, $resp));
    }

    private static function msg($json, $resp)
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
        return '请求失败：HTTP ' . $resp['status'];
    }

    private static function fail($message, $status = 0)
    {
        return array('success' => false, 'status' => $status, 'message' => $message, 'items' => array(), 'total' => 0);
    }
}
