<?php

namespace TypechoPlugin\AstraHub;

use Widget\Base;
use Widget\ActionInterface;
use Widget\User;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

require_once __DIR__ . '/lib/HubSigner.php';
require_once __DIR__ . '/lib/HubClient.php';
require_once __DIR__ . '/lib/CredentialStore.php';
require_once __DIR__ . '/lib/RegisterService.php';
require_once __DIR__ . '/lib/LinksRepository.php';
require_once __DIR__ . '/lib/LinkGroupsRepository.php';
require_once __DIR__ . '/lib/PushService.php';
require_once __DIR__ . '/lib/FriendService.php';
require_once __DIR__ . '/lib/Mailer.php';
require_once __DIR__ . '/lib/PushLogger.php';
require_once __DIR__ . '/lib/Cron.php';

/**
 * AstraHub action 路由总入口：/action/astrahub，按 query 参数 do 分发，统一返回 JSON。
 */
class Action extends Base implements ActionInterface
{
    public function action()
    {
        $do = $this->request->get('do', '');

        if ($do === 'cron') {
            $this->handleCron();
            return;
        }
        if ($do === 'publicStatus') {
            $this->handlePublicStatus();
            return;
        }
        if ($do === 'friendsPublic') {
            $this->handleFriendsPublic();
            return;
        }
        if ($do === 'friendApply') {
            $this->handleFriendApply();
            return;
        }

        $user = User::alloc();
        $user->pass('administrator');

        switch ($do) {
            case 'config':
                $this->handleConfig();
                break;
            case 'saveConfig':
                $this->handleSaveConfig();
                break;
            case 'requestCode':
                $this->handleRequestCode();
                break;
            case 'register':
                $this->handleRegister();
                break;
            case 'boardingSend':
                $this->handleBoardingSend();
                break;
            case 'boardingRestore':
                $this->handleBoardingRestore();
                break;
            case 'wsToken':
                $this->handleWsToken();
                break;
            case 'linksList':
                $this->handleLinksList();
                break;
            case 'linkSave':
                $this->handleLinkSave();
                break;
            case 'linkDelete':
                $this->handleLinkDelete();
                break;
            case 'linksReorder':
                $this->handleLinksReorder();
                break;
            case 'linkGroups':
                $this->handleLinkGroups();
                break;
            case 'linkGroupSave':
                $this->handleLinkGroupSave();
                break;
            case 'linkGroupDelete':
                $this->handleLinkGroupDelete();
                break;
            case 'linkGroupsReorder':
                $this->handleLinkGroupsReorder();
                break;
            case 'pushLinks':
                $this->handlePushLinks();
                break;
            case 'linksPending':
                $this->handleLinksPending();
                break;
            case 'linkApprove':
                $this->handleLinkApprove();
                break;
            case 'linkReject':
                $this->handleLinkReject();
                break;
            case 'planetLinks':
                $this->handlePlanetLinks();
                break;
            case 'friendList':
                $this->handleFriendList();
                break;
            case 'friendCreate':
                $this->handleFriendCreate();
                break;
            case 'friendReview':
                $this->handleFriendReview();
                break;
            case 'friendAck':
                $this->handleFriendAck();
                break;
            case 'friendCancel':
                $this->handleFriendCancel();
                break;
            case 'friendDelete':
                $this->handleFriendDelete();
                break;
            case 'friendRemoveRelation':
                $this->handleFriendRemoveRelation();
                break;
            case 'friendReconcile':
                $this->handleFriendReconcile();
                break;
            case 'friendSyncPeer':
                $this->handleFriendSyncPeer();
                break;
            case 'friendDeletePeer':
                $this->handleFriendDeletePeer();
                break;
            case 'newsBrowse':
                $this->handleHubGet('/v1/planet/rss-deep-space/browse', array('size', 'cursor', 'onlyMyGalaxy'));
                break;
            case 'newsSearch':
                $this->handleHubGet('/v1/planet/rss-deep-space/search', array('q', 'size', 'cursor', 'onlyMyGalaxy'));
                break;
            case 'newsDiscover':
                $this->handleHubGet('/v1/planet/rss-deep-space/discover', array('size', 'cursor'));
                break;
            case 'graphMySite':
                $this->handleGraphMySite();
                break;
            case 'graphNodes':
                $this->handleHubGet('/v1/graph/nodes', array('page', 'size', 'sort'));
                break;
            case 'graphNode':
                $this->handleGraphNode();
                break;
            case 'graphAvatar':
                $this->handleGraphAvatar();
                break;
            case 'pushGraph':
                $this->handlePushGraph();
                break;
            case 'pushLog':
                $this->handlePushLog();
                break;
            case 'pushLogClear':
                $this->handlePushLogClear();
                break;
            default:
                $this->json(array('success' => false, 'message' => 'unknown action: ' . $do), 400);
        }
    }

    private function handleConfig()
    {
        $s = \AstraHub_CredentialStore::load();
        $apiKey = isset($s['credentials']['apiKey']) ? $s['credentials']['apiKey'] : '';
        $s['credentials']['apiKey'] = '';
        $s['credentials']['apiKeyMasked'] = self::maskKey($apiKey);
        $s['credentials']['hasApiKey'] = ($apiKey !== '');
        if ($apiKey !== '') {
            $options = \Widget\Options::alloc();
            $cronUrl = \Typecho\Common::url('/action/astrahub?do=cron&token=' . \AstraHub_CredentialStore::cronToken(), $options->index);
            $s['cronUrl'] = $cronUrl;
        }
        $this->json(array('success' => true, 'data' => $s));
    }

    private function handleSaveConfig()
    {
        $body = $this->readJsonBody();
        if (!is_array($body)) {
            $this->json(array('success' => false, 'message' => 'invalid json'), 400);
            return;
        }
        if (isset($body['credentials']['apiKey'])) {
            unset($body['credentials']['apiKey']);
        }
        \AstraHub_CredentialStore::save($body);
        $this->json(array('success' => true));
    }

    private function handleRequestCode()
    {
        $body = $this->readJsonBody();
        $base = $this->hubBase($body);
        $svc = new \AstraHub_RegisterService($base);
        $result = $svc->requestInvitationCode(
            $this->field($body, 'contactEmail'),
            $this->field($body, 'siteUrl')
        );
        $this->json($result, $result['success'] ? 200 : 400);
    }

    private function handleRegister()
    {
        $body = $this->readJsonBody();
        $base = $this->hubBase($body);
        $svc = new \AstraHub_RegisterService($base);
        $result = $svc->registerWithInvitation(array(
            'invitationCode' => $this->field($body, 'invitationCode'),
            'siteName' => $this->field($body, 'siteName'),
            'siteUrl' => $this->field($body, 'siteUrl'),
            'siteDescription' => $this->field($body, 'siteDescription'),
            'siteRssUrl' => $this->field($body, 'siteRssUrl'),
            'siteAvatarUrl' => $this->field($body, 'siteAvatarUrl'),
            'contactEmail' => $this->field($body, 'contactEmail'),
            'siteNodeName' => $this->field($body, 'siteNodeName'),
            'siteNodeAvatar' => $this->field($body, 'siteNodeAvatar'),
        ));

        if ($result['success']) {
            \AstraHub_CredentialStore::saveCredentials($result['siteId'], $result['apiKey'], $result['createdAt']);
            $this->persistConnectionFromBody($body, $result);
        }
        $safe = $result;
        unset($safe['apiKey']);
        $safe['hasApiKey'] = $result['success'];
        $this->json($safe, $result['success'] ? 200 : 400);
    }

    private function handleBoardingSend()
    {
        $body = $this->readJsonBody();
        $base = $this->hubBase($body);
        $svc = new \AstraHub_RegisterService($base);
        $result = $svc->sendBoardingCode($this->field($body, 'contactEmail'));
        $this->json($result, $result['success'] ? 200 : 400);
    }

    private function handleBoardingRestore()
    {
        $body = $this->readJsonBody();
        $base = $this->hubBase($body);
        $svc = new \AstraHub_RegisterService($base);
        $result = $svc->restoreByBoardingCode(
            $this->field($body, 'contactEmail'),
            $this->field($body, 'code')
        );

        if ($result['success']) {
            \AstraHub_CredentialStore::saveCredentials($result['siteId'], $result['apiKey'], $result['createdAt']);
            \AstraHub_CredentialStore::save(array('connection' => array(
                'siteName' => $result['siteName'],
                'siteUrl' => $result['siteUrl'],
                'contactEmail' => $result['contactEmail'],
                'siteDescription' => $result['description'],
                'siteRssUrl' => $result['rssUrl'],
                'siteNodeName' => $result['nodeName'],
                'siteNodeAvatar' => $result['nodeAvatar'],
            )));
        }
        $safe = $result;
        unset($safe['apiKey']);
        $safe['hasApiKey'] = $result['success'];
        $this->json($safe, $result['success'] ? 200 : 400);
    }

    private function handleLinksList()
    {
        $this->json(array('success' => true, 'items' => \AstraHub_LinksRepository::all()));
    }

    private function handleLinkSave()
    {
        $body = $this->readJsonBody();
        $name = $this->field($body, 'siteName');
        if ($name === '') {
            $name = $this->field($body, 'name');
        }
        $url = $this->field($body, 'siteUrl');
        if ($url === '') {
            $url = $this->field($body, 'url');
        }
        if ($name === '' || $url === '') {
            $this->json(array('success' => false, 'message' => '名称与链接为必填'), 400);
            return;
        }
        $data = array(
            'siteName' => $name,
            'siteUrl' => $url,
            'avatarUrl' => $this->field($body, 'avatarUrl') !== '' ? $this->field($body, 'avatarUrl') : $this->field($body, 'image'),
            'summary' => $this->field($body, 'summary') !== '' ? $this->field($body, 'summary') : $this->field($body, 'description'),
            'rssUrl' => $this->field($body, 'rssUrl'),
            'applicantEmail' => $this->field($body, 'applicantEmail') !== '' ? $this->field($body, 'applicantEmail') : $this->field($body, 'email'),
            'targetSiteId' => $this->field($body, 'targetSiteId'),
            'relationKind' => $this->field($body, 'relationKind'),
        );
        if (isset($body['groupId'])) {
            $data['groupId'] = (int) $body['groupId'];
        }
        if (isset($body['reviewState'])) {
            $data['reviewState'] = (int) $body['reviewState'];
        } elseif (isset($body['isActive'])) {
            $data['reviewState'] = $body['isActive'] ? 1 : 0;
        }
        $flid = isset($body['flid']) ? (int) $body['flid'] : 0;
        if ($flid <= 0) {
            $flid = isset($body['lid']) ? (int) $body['lid'] : 0;
        }
        if ($flid > 0) {
            try {
                \AstraHub_LinksRepository::update($flid, $data);
            } catch (\Exception $e) {
                $this->json(array('success' => false, 'message' => '保存失败：' . $e->getMessage()), 500);
                return;
            }
        } else {
            try {
                $flid = (int) \AstraHub_LinksRepository::insert($data);
            } catch (\Exception $e) {
                $this->json(array('success' => false, 'message' => '保存失败：' . $e->getMessage()), 500);
                return;
            }
        }
        $this->json(array('success' => true, 'flid' => $flid, 'lid' => $flid));
    }

    private function handleLinkDelete()
    {
        $body = $this->readJsonBody();
        $flid = isset($body['flid']) ? (int) $body['flid'] : 0;
        if ($flid <= 0) {
            $flid = isset($body['lid']) ? (int) $body['lid'] : 0;
        }
        if ($flid <= 0) {
            $this->json(array('success' => false, 'message' => '缺少 flid'), 400);
            return;
        }
        \AstraHub_LinksRepository::delete($flid);
        $this->json(array('success' => true));
    }

    private function handleLinksReorder()
    {
        $body = $this->readJsonBody();
        $flids = isset($body['flids']) && is_array($body['flids']) ? $body['flids'] : array();
        if (empty($flids)) {
            $flids = isset($body['lids']) && is_array($body['lids']) ? $body['lids'] : array();
        }
        \AstraHub_LinksRepository::reorder($flids);
        $this->json(array('success' => true));
    }

    private function handleLinkGroups()
    {
        $as = (string) $this->request->get('as', 'options');
        if ($as === 'manage') {
            $this->json(array('success' => true, 'items' => \AstraHub_LinkGroupsRepository::all()));
            return;
        }
        $this->json(array('success' => true, 'data' => array('items' => \AstraHub_LinkGroupsRepository::options()), 'items' => \AstraHub_LinkGroupsRepository::options()));
    }

    private function handleLinkGroupSave()
    {
        $body = $this->readJsonBody();
        $displayName = $this->field($body, 'displayName');
        if ($displayName === '') {
            $displayName = $this->field($body, 'name');
        }
        $gid = isset($body['gid']) ? (int) $body['gid'] : 0;
        if ($gid > 0) {
            \AstraHub_LinkGroupsRepository::update($gid, array(
                'displayName' => $displayName,
            ));
            $this->json(array('success' => true, 'gid' => $gid));
            return;
        }
        if ($displayName === '') {
            $this->json(array('success' => false, 'message' => '分组名称为必填'), 400);
            return;
        }
        $name = $this->field($body, 'name');
        $slugProbe = $name !== '' ? $name : $displayName;
        $existing = \AstraHub_LinkGroupsRepository::findByName($slugProbe);
        if ($existing) {
            $this->json(array('success' => false, 'message' => '分组已存在'), 409);
            return;
        }
        try {
            $gid = (int) \AstraHub_LinkGroupsRepository::insert(array(
                'displayName' => $displayName,
                'name' => $name,
            ));
        } catch (\Exception $e) {
            $this->json(array('success' => false, 'message' => '保存分组失败：' . $e->getMessage()), 500);
            return;
        }
        $this->json(array('success' => true, 'gid' => $gid));
    }

    private function handleLinkGroupDelete()
    {
        $body = $this->readJsonBody();
        $gid = isset($body['gid']) ? (int) $body['gid'] : 0;
        if ($gid <= 0) {
            $this->json(array('success' => false, 'message' => '缺少 gid'), 400);
            return;
        }
        \AstraHub_LinkGroupsRepository::delete($gid);
        $this->json(array('success' => true));
    }

    private function handleLinkGroupsReorder()
    {
        $body = $this->readJsonBody();
        $gids = isset($body['gids']) && is_array($body['gids']) ? $body['gids'] : array();
        \AstraHub_LinkGroupsRepository::reorder($gids);
        $this->json(array('success' => true));
    }

    private function handleLinksPending()
    {
        $this->json(array('success' => true, 'items' => \AstraHub_LinksRepository::allPending()));
    }

    private function handleLinkApprove()
    {
        $body = $this->readJsonBody();
        $flid = isset($body['flid']) ? (int) $body['flid'] : 0;
        if ($flid <= 0) {
            $flid = isset($body['lid']) ? (int) $body['lid'] : 0;
        }
        if ($flid <= 0) {
            $this->json(array('success' => false, 'message' => '缺少 flid'), 400);
            return;
        }
        $link = \AstraHub_LinksRepository::get($flid);
        if (!$link) {
            $this->json(array('success' => false, 'message' => '友链不存在'), 404);
            return;
        }
        \AstraHub_LinksRepository::approve($flid);
        $this->notifyApplicantForApproval($link, true, '');
        try {
            \AstraHub_PushService::pushLinkEdges();
        } catch (\Exception $e) {
            // 上报失败不阻断审核结果，兜底 cron 会补推
        }
        $this->json(array('success' => true));
    }

    private function handleLinkReject()
    {
        $body = $this->readJsonBody();
        $flid = isset($body['flid']) ? (int) $body['flid'] : 0;
        if ($flid <= 0) {
            $flid = isset($body['lid']) ? (int) $body['lid'] : 0;
        }
        if ($flid <= 0) {
            $this->json(array('success' => false, 'message' => '缺少 flid'), 400);
            return;
        }
        $link = \AstraHub_LinksRepository::get($flid);
        if (!$link) {
            $this->json(array('success' => false, 'message' => '友链不存在'), 404);
            return;
        }
        $reason = $this->field($body, 'reason');
        if ($reason === '') {
            $reason = '不符合本站友链收录规则';
        }
        \AstraHub_LinksRepository::reject($flid);
        $this->notifyApplicantForApproval($link, false, $reason);
        try {
            \AstraHub_PushService::pushLinkEdges();
        } catch (\Exception $e) {
            // 上报失败不阻断审核结果，兜底 cron 会补推
        }
        $this->json(array('success' => true));
    }

    private function handlePushLinks()
    {
        $result = \AstraHub_PushService::pushLinkEdges();
        $this->json($result, $result['success'] ? 200 : 400);
    }

    private function handlePlanetLinks()
    {
        $cred = \AstraHub_CredentialStore::credentials();
        if ($cred['siteId'] === '' || $cred['apiKey'] === '') {
            $this->json(array('success' => false, 'message' => '站点未接入'), 400);
            return;
        }
        $s = \AstraHub_CredentialStore::load();
        $base = isset($s['connection']['hubBaseUrl']) && $s['connection']['hubBaseUrl'] !== ''
            ? $s['connection']['hubBaseUrl'] : 'https://astra.aobp.cn';

        $allowed = array('size', 'cursor', 'tag', 'keyword', 'relation');
        $params = array();
        foreach ($allowed as $k) {
            $v = $this->request->get($k, '');
            if ($v !== '' && $v !== null) {
                $params[$k] = (string) $v;
            }
        }
        $path = '/v1/planet/links';
        if (!empty($params)) {
            $path .= '?' . http_build_query($params);
        }

        $client = new \AstraHub_HubClient($base, 20);
        $resp = $client->signedRequest('GET', $path, null, $cred['siteId'], $cred['apiKey']);
        if (!$resp['ok'] || !is_array($resp['json'])) {
            $this->json(array('success' => false, 'message' => $this->hubMessage(is_array($resp['json']) ? $resp['json'] : array(), $resp)), $resp['status'] ?: 502);
            return;
        }
        $out = $resp['json'];
        $out['success'] = true;
        $this->json($out);
    }

    private function handleFriendList()
    {
        $box = $this->request->get('box', 'inbox');
        $status = $this->request->get('status', '');
        $result = \AstraHub_FriendService::list($box, $status);
        $this->json($result, $result['success'] ? 200 : ($result['status'] ?: 400));
    }

    private function handleFriendCreate()
    {
        $body = $this->readJsonBody();
        $result = \AstraHub_FriendService::create(
            $this->field($body, 'toSiteId'),
            $this->field($body, 'message'),
            $this->field($body, 'linkGroupName')
        );
        $this->json($result, $result['success'] ? 200 : ($result['status'] ?: 400));
    }

    private function handleFriendReview()
    {
        $body = $this->readJsonBody();
        $inviteId = $this->field($body, 'inviteId');
        if ($inviteId === '') {
            $this->json(array('success' => false, 'message' => '缺少 inviteId'), 400);
            return;
        }
        $approved = isset($body['approved']) ? (bool) $body['approved'] : false;
        $linkGroupName = $this->field($body, 'linkGroupName');
        $result = \AstraHub_FriendService::review(
            $inviteId,
            $approved,
            $this->field($body, 'reason'),
            $linkGroupName
        );
        if ($approved && !empty($result['success']) && !empty($result['invitation'])) {
            $peer = $this->peerFromInvitation($result['invitation']);
            if ($peer !== null) {
                \AstraHub_FriendService::reconcileLocalLink($peer, 'mutual', $linkGroupName, 'invitation');
                \AstraHub_PushService::pushLinkEdges();
            }
        }
        $this->json($result, $result['success'] ? 200 : ($result['status'] ?: 400));
    }

    private function peerFromInvitation($invitation)
    {
        if (!is_array($invitation)) {
            return null;
        }
        $cred = \AstraHub_CredentialStore::credentials();
        $selfId = isset($cred['siteId']) ? (string) $cred['siteId'] : '';
        $from = isset($invitation['fromSite']) && is_array($invitation['fromSite']) ? $invitation['fromSite'] : array();
        $to = isset($invitation['toSite']) && is_array($invitation['toSite']) ? $invitation['toSite'] : array();
        $fromId = isset($from['siteId']) ? (string) $from['siteId'] : '';
        $peer = ($selfId !== '' && $fromId === $selfId) ? $to : $from;
        if (empty($peer) || empty($peer['siteUrl']) || empty($peer['siteName'])) {
            return null;
        }
        return array(
            'siteId' => isset($peer['siteId']) ? (string) $peer['siteId'] : '',
            'siteName' => (string) $peer['siteName'],
            'siteUrl' => (string) $peer['siteUrl'],
            'description' => isset($peer['description']) ? (string) $peer['description'] : '',
            'avatarUrl' => isset($peer['avatarUrl']) ? (string) $peer['avatarUrl'] : '',
            'rssUrl' => isset($peer['rssUrl']) ? (string) $peer['rssUrl'] : '',
        );
    }

    private function handleFriendAck()
    {
        $body = $this->readJsonBody();
        $result = \AstraHub_FriendService::ack($this->field($body, 'inviteId'), $this->field($body, 'lastError'));
        $this->json($result, $result['success'] ? 200 : ($result['status'] ?: 400));
    }

    private function handleFriendCancel()
    {
        $body = $this->readJsonBody();
        $result = \AstraHub_FriendService::cancel($this->field($body, 'inviteId'));
        $this->json($result, $result['success'] ? 200 : ($result['status'] ?: 400));
    }

    private function handleFriendDelete()
    {
        $body = $this->readJsonBody();
        $result = \AstraHub_FriendService::delete($this->field($body, 'inviteId'));
        $this->json($result, $result['success'] ? 200 : ($result['status'] ?: 400));
    }

    private function handleFriendRemoveRelation()
    {
        $body = $this->readJsonBody();
        $peerSiteId = $this->field($body, 'peerSiteId');
        if ($peerSiteId === '') {
            $this->json(array('success' => false, 'message' => '缺少 peerSiteId'), 400);
            return;
        }
        $result = \AstraHub_FriendService::removeRelation($peerSiteId, $this->field($body, 'reason'));
        $this->json($result, $result['success'] ? 200 : ($result['status'] ?: 400));
    }

    private function handleFriendReconcile()
    {
        $body = $this->readJsonBody();
        $peer = array(
            'siteId' => $this->field($body, 'peerSiteId'),
            'siteName' => $this->field($body, 'peerSiteName'),
            'siteUrl' => $this->field($body, 'peerSiteUrl'),
            'description' => $this->field($body, 'peerDescription'),
            'avatarUrl' => $this->field($body, 'peerAvatarUrl'),
            'rssUrl' => $this->field($body, 'peerRssUrl'),
        );
        $relationKind = $this->field($body, 'relationKind');
        if ($relationKind === '') {
            $relationKind = 'mutual';
        }
        $result = \AstraHub_FriendService::reconcileLocalLink($peer, $relationKind, $this->field($body, 'linkGroupName'), 'invitation');
        \AstraHub_PushLogger::log('reconcile', 'friendReconcile', array($peer), array(
            'success' => !empty($result['success']),
            'status' => !empty($result['success']) ? 200 : 400,
            'message' => isset($result['message']) ? $result['message'] : '',
        ));
        $this->json($result, $result['success'] ? 200 : 400);
    }

    private function handleFriendSyncPeer()
    {
        $body = $this->readJsonBody();
        $peerSiteId = $this->field($body, 'peerSiteId');
        if ($peerSiteId === '') {
            $this->json(array('success' => false, 'message' => '缺少 peerSiteId'), 400);
            return;
        }
        $profile = array(
            'name' => $this->field($body, 'name'),
            'url' => $this->field($body, 'url'),
            'description' => $this->field($body, 'description'),
            'avatarUrl' => $this->field($body, 'avatarUrl'),
            'rssUrl' => $this->field($body, 'rssUrl'),
        );
        try {
            $result = \AstraHub_FriendService::updateLocalLinkByPeerSiteId($peerSiteId, $profile);
        } catch (\Exception $e) {
            $this->json(array('success' => false, 'message' => '同步失败：' . $e->getMessage()), 500);
            return;
        }
        $this->json($result, $result['success'] ? 200 : 400);
    }

    private function handleFriendDeletePeer()
    {
        $body = $this->readJsonBody();
        $peerSiteId = $this->field($body, 'peerSiteId');
        $peerUrl = $this->field($body, 'peerUrl');
        if ($peerSiteId === '' && $peerUrl === '') {
            $this->json(array('success' => false, 'message' => '缺少 peerSiteId 或 peerUrl'), 400);
            return;
        }
        try {
            $result = \AstraHub_FriendService::deleteLocalLinkByPeer($peerSiteId, $peerUrl);
        } catch (\Exception $e) {
            $this->json(array('success' => false, 'message' => '删除失败：' . $e->getMessage()), 500);
            return;
        }
        $this->json($result, $result['success'] ? 200 : 400);
    }

    private function handleHubGet($hubPath, array $allowedParams)
    {
        $cred = \AstraHub_CredentialStore::credentials();
        if ($cred['siteId'] === '' || $cred['apiKey'] === '') {
            $this->json(array('success' => false, 'message' => '站点未接入'), 400);
            return;
        }
        $base = $this->storedBase();
        $params = array();
        foreach ($allowedParams as $k) {
            $v = $this->request->get($k, '');
            if ($v !== '' && $v !== null) {
                $params[$k] = (string) $v;
            }
        }
        $path = $hubPath;
        if (!empty($params)) {
            $path .= '?' . http_build_query($params);
        }
        $client = new \AstraHub_HubClient($base, 25);
        $resp = $client->signedRequest('GET', $path, null, $cred['siteId'], $cred['apiKey']);
        if (!$resp['ok'] || !is_array($resp['json'])) {
            $this->json(array('success' => false, 'message' => $this->hubMessage(is_array($resp['json']) ? $resp['json'] : array(), $resp)), $resp['status'] ?: 502);
            return;
        }
        $out = $resp['json'];
        $out['success'] = true;
        $this->json($out);
    }

    private function handleGraphMySite()
    {
        $cred = \AstraHub_CredentialStore::credentials();
        if ($cred['siteId'] === '') {
            $this->json(array('success' => false, 'message' => '站点未接入'), 400);
            return;
        }
        $base = $this->storedBase();
        $path = '/v1/graph/sites/' . $cred['siteId'];
        $client = new \AstraHub_HubClient($base, 25);
        $resp = $client->signedRequest('GET', $path, null, $cred['siteId'], $cred['apiKey']);
        if (!$resp['ok'] || !is_array($resp['json'])) {
            $this->json(array('success' => false, 'message' => $this->hubMessage(is_array($resp['json']) ? $resp['json'] : array(), $resp)), $resp['status'] ?: 502);
            return;
        }
        $out = $resp['json'];
        $out['success'] = true;
        $this->json($out);
    }

    private function handleGraphNode()
    {
        $cred = \AstraHub_CredentialStore::credentials();
        if ($cred['siteId'] === '' || $cred['apiKey'] === '') {
            $this->json(array('success' => false, 'message' => '站点未接入'), 400);
            return;
        }
        $nodeId = (string) $this->request->get('nodeId', '');
        if ($nodeId === '' || !$this->isSafePathSegment($nodeId)) {
            $this->json(array('success' => false, 'message' => '缺少或非法 nodeId'), 400);
            return;
        }
        $size = (string) $this->request->get('size', '100');
        $base = $this->storedBase();
        // nodeId 段须用未编码原文进签名与请求 URL（Hub 按解码后的 path 验签，nodeId 多含中文）
        $path = '/v1/graph/nodes/' . $nodeId . '?size=' . rawurlencode($size);
        $client = new \AstraHub_HubClient($base, 25);
        $resp = $client->signedRequest('GET', $path, null, $cred['siteId'], $cred['apiKey']);
        if (!$resp['ok'] || !is_array($resp['json'])) {
            $this->json(array('success' => false, 'message' => $this->hubMessage(is_array($resp['json']) ? $resp['json'] : array(), $resp)), $resp['status'] ?: 502);
            return;
        }
        $out = $resp['json'];
        $out['success'] = true;
        $this->json($out);
    }

    /**
     * 同源头像代理：取远程头像字节后同源返回，绕过友链站缺失的 CORS 头。
     * SSRF 防护：解析目标主机 IP，命中私网/环回/链路本地/元数据地址即拒绝。
     */
    private function handleGraphAvatar()
    {
        $raw = trim((string) $this->request->get('url', ''));
        if ($raw === '' || !preg_match('#^https?://#i', $raw)) {
            $this->rawError(400, 'invalid url');
            return;
        }
        $parts = parse_url($raw);
        if ($parts === false || empty($parts['host'])) {
            $this->rawError(400, 'invalid url');
            return;
        }
        if (!$this->isPublicHost($parts['host'])) {
            $this->rawError(400, 'blocked host');
            return;
        }
        $maxBytes = 2 * 1024 * 1024;
        $body = '';
        $contentType = 'image/png';
        if (function_exists('curl_init')) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $raw);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 8);
            curl_setopt($ch, CURLOPT_TIMEOUT, 12);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
            curl_setopt($ch, CURLOPT_USERAGENT, 'AstraHub-Plugin-Avatar/1.0');
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: image/*,*/*;q=0.8'));
            curl_setopt($ch, CURLOPT_BUFFERSIZE, 16384);
            curl_setopt($ch, CURLOPT_NOPROGRESS, false);
            curl_setopt($ch, CURLOPT_PROGRESSFUNCTION, function ($c, $dlTotal, $dlNow) use ($maxBytes) {
                return ($dlNow > $maxBytes) ? 1 : 0;
            });
            $body = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $ct = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
            curl_close($ch);
            if ($body === false || $status < 200 || $status >= 300) {
                $this->rawError(502, 'upstream failed');
                return;
            }
            if ($ct !== '') {
                $contentType = $ct;
            }
        } else {
            $this->rawError(502, 'curl unavailable');
            return;
        }
        if ($body === '' || strlen($body) > $maxBytes) {
            $this->rawError(413, 'payload too large or empty');
            return;
        }
        if (!headers_sent()) {
            http_response_code(200);
            header('Content-Type: ' . $contentType);
            header('Cache-Control: public, max-age=86400');
        }
        echo $body;
        exit;
    }

    private function isSafePathSegment($value)
    {
        $value = trim((string) $value);
        if ($value === '' || strlen($value) > 256) {
            return false;
        }
        if (strpos($value, '/') !== false || strpos($value, '?') !== false
            || strpos($value, '#') !== false || strpos($value, "\0") !== false) {
            return false;
        }
        return true;
    }

    private function isPublicHost($host)
    {
        $host = trim((string) $host);
        if ($host === '') {
            return false;
        }
        $ips = array();
        $records = @dns_get_record($host, DNS_A + DNS_AAAA);
        if (is_array($records)) {
            foreach ($records as $r) {
                if (isset($r['ip'])) {
                    $ips[] = $r['ip'];
                }
                if (isset($r['ipv6'])) {
                    $ips[] = $r['ipv6'];
                }
            }
        }
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $ips[] = $host;
        }
        if (empty($ips)) {
            return false;
        }
        foreach ($ips as $ip) {
            if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return false;
            }
        }
        return true;
    }

    private function storedBase()
    {
        $s = \AstraHub_CredentialStore::load();
        $base = isset($s['connection']['hubBaseUrl']) ? trim((string) $s['connection']['hubBaseUrl']) : '';
        return $base !== '' ? $base : 'https://astra.aobp.cn';
    }

    private function handlePushGraph()
    {
        $result = \AstraHub_PushService::pushLinkEdges();
        $this->json($result, $result['success'] ? 200 : 400);
    }

    private function handlePushLog()
    {
        $this->json(array('success' => true, 'items' => \AstraHub_PushLogger::readAll()));
    }

    private function handlePushLogClear()
    {
        \AstraHub_PushLogger::clear();
        $this->json(array('success' => true));
    }

    private function handleCron()
    {
        $token = (string) $this->request->get('token', '');
        $expected = \AstraHub_CredentialStore::cronToken();
        if ($token === '' || !hash_equals($expected, $token)) {
            $this->json(array('success' => false, 'message' => 'forbidden'), 403);
            return;
        }
        $result = \AstraHub_Cron::tick(true);
        $this->json(array('success' => true, 'result' => $result));
    }

    private function handleFriendsPublic()
    {
        $links = \AstraHub_LinksRepository::allApproved();
        $items = array();
        foreach ($links as $link) {
            $items[] = array(
                'siteName' => $link['siteName'],
                'siteUrl' => $link['siteUrl'],
                'avatarUrl' => $link['avatarUrl'],
                'summary' => $link['summary'],
                'rssUrl' => $link['rssUrl'],
            );
        }
        $this->json(array('success' => true, 'items' => $items));
    }

    private function handleFriendApply()
    {
        if (!$this->request->isPost()) {
            $this->json(array('success' => false, 'message' => 'POST required'), 405);
            return;
        }

        $body = $this->readJsonBody();

        $honeypot = trim((string) (isset($body['ah_hp']) ? $body['ah_hp'] : ''));
        if ($honeypot !== '') {
            $this->json(array('success' => true, 'message' => 'submitted'));
            return;
        }

        $clientIp = $this->getClientIp();
        if ($this->hitApplyRateLimit($clientIp, 8, 600)) {
            $this->json(array('success' => false, 'message' => 'rate limited'), 429);
            return;
        }

        $siteUrl = $this->sanitizeUrl($this->field($body, 'siteUrl'));
        if ($siteUrl === '') {
            $this->json(array('success' => false, 'message' => 'siteUrl invalid'), 400);
            return;
        }
        $siteName = $this->sanitizeText($this->field($body, 'siteName'), 200);
        if ($siteName === '') {
            $this->json(array('success' => false, 'message' => 'siteName required'), 400);
            return;
        }
        $applicantEmail = trim($this->field($body, 'applicantEmail'));
        if ($applicantEmail === '' || !filter_var($applicantEmail, FILTER_VALIDATE_EMAIL)) {
            $this->json(array('success' => false, 'message' => 'email required'), 400);
            return;
        }

        $s = \AstraHub_CredentialStore::load();
        $selfUrl = isset($s['connection']['siteUrl']) ? (string) $s['connection']['siteUrl'] : '';
        if ($selfUrl !== '' && $this->hostOf($siteUrl) === $this->hostOf($selfUrl)) {
            $this->json(array('success' => false, 'message' => 'self link not allowed'), 400);
            return;
        }

        $existing = \AstraHub_LinksRepository::findByUrl($siteUrl);
        if ($existing) {
            $this->json(array('success' => false, 'message' => 'duplicate'), 409);
            return;
        }

        $avatarUrl = $this->sanitizeUrl($this->field($body, 'avatarUrl'));
        $rssUrl = $this->sanitizeUrl($this->field($body, 'rssUrl'));
        $summary = $this->sanitizeText($this->field($body, 'summary'), 500);

        $flid = \AstraHub_LinksRepository::applyFromVisitor(array(
            'siteName' => $siteName,
            'siteUrl' => $siteUrl,
            'avatarUrl' => $avatarUrl,
            'summary' => $summary,
            'rssUrl' => $rssUrl,
            'applicantEmail' => $applicantEmail,
        ), $clientIp);

        if (!$flid) {
            $this->json(array('success' => false, 'message' => 'insert failed'), 500);
            return;
        }

        $this->notifyAdminForApply(array(
            'siteName' => $siteName,
            'siteUrl' => $siteUrl,
            'avatarUrl' => $avatarUrl,
            'summary' => $summary,
            'rssUrl' => $rssUrl,
            'applicantEmail' => $applicantEmail,
        ));

        $this->json(array('success' => true, 'message' => 'submitted'));
    }

    private function getClientIp()
    {
        $keys = array('HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR');
        foreach ($keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = trim((string) $_SERVER[$key]);
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        return '';
    }

    private function hitApplyRateLimit($ip, $maxCount = 8, $windowSeconds = 600)
    {
        $ip = trim((string) $ip);
        if ($ip === '') {
            return false;
        }

        $s = \AstraHub_CredentialStore::load();
        $rateLimit = isset($s['friendApplyRateLimit']) && is_array($s['friendApplyRateLimit'])
            ? $s['friendApplyRateLimit'] : array();

        $now = time();
        $key = md5($ip);
        $records = isset($rateLimit[$key]) && is_array($rateLimit[$key]) ? $rateLimit[$key] : array();

        $valid = array();
        foreach ($records as $ts) {
            $ts = (int) $ts;
            if ($ts > 0 && ($now - $ts) <= (int) $windowSeconds) {
                $valid[] = $ts;
            }
        }

        $limited = count($valid) >= (int) $maxCount;
        if (!$limited) {
            $valid[] = $now;
        }

        $newRateLimit = array();
        foreach ($rateLimit as $k => $v) {
            if (is_array($v)) {
                $filtered = array();
                foreach ($v as $ts) {
                    if ((int) $ts > 0 && ($now - (int) $ts) <= 86400) {
                        $filtered[] = (int) $ts;
                    }
                }
                if (!empty($filtered)) {
                    $newRateLimit[$k] = $filtered;
                }
            }
        }
        $newRateLimit[$key] = $valid;
        \AstraHub_CredentialStore::save(array('friendApplyRateLimit' => $newRateLimit));

        return $limited;
    }

    private function sanitizeUrl($value)
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }
        if (!filter_var($value, FILTER_VALIDATE_URL)) {
            return '';
        }
        $parts = @parse_url($value);
        $scheme = isset($parts['scheme']) ? strtolower((string) $parts['scheme']) : '';
        if (!in_array($scheme, array('http', 'https'), true)) {
            return '';
        }
        return $value;
    }

    private function sanitizeText($value, $maxLen)
    {
        $value = trim((string) $value);
        $value = preg_replace('/[\x00-\x1F\x7F]/u', '', $value);
        if ($maxLen > 0 && function_exists('mb_substr')) {
            $value = mb_substr($value, 0, (int) $maxLen, 'UTF-8');
        } elseif ($maxLen > 0) {
            $value = substr($value, 0, (int) $maxLen);
        }
        return trim((string) $value);
    }

    private function hostOf($url)
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

    private function notifyAdminForApply(array $link)
    {
        $s = \AstraHub_CredentialStore::load();
        $fa = isset($s['friendApply']) && is_array($s['friendApply']) ? $s['friendApply'] : array();

        $enabled = isset($fa['enabled']) ? (bool) $fa['enabled'] : true;
        if (!$enabled) {
            return false;
        }

        $adminEmail = isset($fa['notifyEmail']) ? trim((string) $fa['notifyEmail']) : '';
        if ($adminEmail === '' || !filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $siteName = isset($s['connection']['siteName']) ? (string) $s['connection']['siteName'] : 'AstraHub Site';
        $siteUrl = isset($s['connection']['siteUrl']) ? (string) $s['connection']['siteUrl'] : '';

        $contentHtml = '<p style="margin:0 0 16px;font-size:14px;color:#374151;line-height:1.6">收到一条新的友链申请，请前往后台审核。</p>'
            . '<div style="border:1px solid #e5e7eb;border-radius:12px;overflow:hidden">'
            . '<table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;font-size:14px">'
            . '<tr><td style="padding:10px 16px;background:#f8fafc;color:#6b7280;width:90px;border-bottom:1px solid #e5e7eb">站点名称</td>'
            . '<td style="padding:10px 16px;background:#f8fafc;color:#111827;font-weight:600;border-bottom:1px solid #e5e7eb">' . htmlspecialchars($link['siteName'], ENT_QUOTES, 'UTF-8') . '</td></tr>'
            . '<tr><td style="padding:10px 16px;color:#6b7280;border-bottom:1px solid #e5e7eb">站点地址</td>'
            . '<td style="padding:10px 16px;border-bottom:1px solid #e5e7eb"><a href="' . htmlspecialchars($link['siteUrl'], ENT_QUOTES, 'UTF-8') . '" style="color:#0061a4">' . htmlspecialchars($link['siteUrl'], ENT_QUOTES, 'UTF-8') . '</a></td></tr>'
            . '<tr><td style="padding:10px 16px;background:#f8fafc;color:#6b7280;border-bottom:1px solid #e5e7eb">描述</td>'
            . '<td style="padding:10px 16px;background:#f8fafc;color:#374151;border-bottom:1px solid #e5e7eb">' . htmlspecialchars($link['summary'], ENT_QUOTES, 'UTF-8') . '</td></tr>'
            . '<tr><td style="padding:10px 16px;color:#6b7280">邮箱</td>'
            . '<td style="padding:10px 16px;color:#374151">' . htmlspecialchars($link['applicantEmail'], ENT_QUOTES, 'UTF-8') . '</td></tr>'
            . '</table></div>';

        $subject = '【' . $siteName . '】收到新的友链申请：' . $link['siteName'];
        $fullHtml = \AstraHub_Mailer::buildEmailHtml($contentHtml, $siteName, $siteUrl, '友链申请通知');

        $options = array(
            'driver' => isset($fa['mailDriver']) ? (string) $fa['mailDriver'] : 'phpmail',
            'from_email' => $adminEmail,
            'from_name' => $siteName,
            'reply_to' => $link['applicantEmail'],
            'smtp_host' => isset($fa['smtpHost']) ? (string) $fa['smtpHost'] : '',
            'smtp_port' => isset($fa['smtpPort']) ? (int) $fa['smtpPort'] : 587,
            'smtp_user' => isset($fa['smtpUser']) ? (string) $fa['smtpUser'] : '',
            'smtp_pass' => isset($fa['smtpPass']) ? (string) $fa['smtpPass'] : '',
            'smtp_secure' => isset($fa['smtpSecure']) ? (string) $fa['smtpSecure'] : 'tls',
        );

        return \AstraHub_Mailer::send($adminEmail, $subject, $fullHtml, $options);
    }

    private function notifyApplicantForApproval(array $link, $approved, $reason = '')
    {
        $applicantEmail = isset($link['applicantEmail']) ? trim((string) $link['applicantEmail']) : '';
        if ($applicantEmail === '' || !filter_var($applicantEmail, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $s = \AstraHub_CredentialStore::load();
        $fa = isset($s['friendApply']) && is_array($s['friendApply']) ? $s['friendApply'] : array();

        $enabled = isset($fa['enabled']) ? (bool) $fa['enabled'] : true;
        if (!$enabled) {
            return false;
        }

        $siteName = isset($s['connection']['siteName']) ? (string) $s['connection']['siteName'] : 'AstraHub Site';
        $siteUrl = isset($s['connection']['siteUrl']) ? (string) $s['connection']['siteUrl'] : '';
        $adminEmail = isset($fa['notifyEmail']) ? trim((string) $fa['notifyEmail']) : '';

        $linkName = isset($link['siteName']) ? (string) $link['siteName'] : '';
        $linkUrl = isset($link['siteUrl']) ? (string) $link['siteUrl'] : '';

        if ($approved) {
            $subject = '【' . $siteName . '】你的友链申请已通过';
            $contentHtml = '<div style="text-align:center;padding-bottom:20px">'
                . '<div style="display:inline-flex;align-items:center;justify-content:center;width:56px;height:56px;border-radius:50%;background:#e8f3ff;font-size:26px">&#10003;</div>'
                . '<p style="margin:10px 0 0;font-size:20px;font-weight:700;color:#0061a4">申请已通过！</p></div>'
                . '<p style="margin:0 0 20px;font-size:14px;color:#374151;line-height:1.7">Hi <strong>' . htmlspecialchars($linkName, ENT_QUOTES, 'UTF-8') . '</strong>，'
                . '你提交至 <strong>' . htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8') . '</strong> 的友链申请已审核通过，感谢你的申请！</p>'
                . '<div style="border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;margin-bottom:20px">'
                . '<table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;font-size:14px">'
                . '<tr><td style="padding:10px 16px;background:#f8fafc;color:#6b7280;width:90px">友链地址</td>'
                . '<td style="padding:10px 16px;background:#f8fafc"><a href="' . htmlspecialchars($linkUrl, ENT_QUOTES, 'UTF-8') . '" style="color:#0061a4;font-weight:600">' . htmlspecialchars($linkUrl, ENT_QUOTES, 'UTF-8') . '</a></td></tr>'
                . '</table></div>'
                . '<p style="margin:0;font-size:14px;color:#6b7280">&#127760; 欢迎互链，期待与你交流！</p>';
        } else {
            if ($reason === '') {
                $reason = '不符合本站友链收录规则';
            }
            $subject = '【' . $siteName . '】你的友链申请未通过';
            $contentHtml = '<p style="margin:0 0 20px;font-size:14px;color:#374151;line-height:1.7">Hi <strong>' . htmlspecialchars($linkName, ENT_QUOTES, 'UTF-8') . '</strong>，'
                . '很遗憾，你提交至 <strong>' . htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8') . '</strong> 的友链申请本次未能通过审核。</p>'
                . '<div style="border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;margin-bottom:20px">'
                . '<table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;font-size:14px">'
                . '<tr><td style="padding:10px 16px;background:#f8fafc;color:#6b7280;width:90px;border-bottom:1px solid #e5e7eb">友链地址</td>'
                . '<td style="padding:10px 16px;background:#f8fafc;border-bottom:1px solid #e5e7eb"><a href="' . htmlspecialchars($linkUrl, ENT_QUOTES, 'UTF-8') . '" style="color:#0061a4">' . htmlspecialchars($linkUrl, ENT_QUOTES, 'UTF-8') . '</a></td></tr>'
                . '<tr><td style="padding:10px 16px;color:#6b7280">驳回原因</td>'
                . '<td style="padding:10px 16px;color:#374151">' . htmlspecialchars($reason, ENT_QUOTES, 'UTF-8') . '</td></tr>'
                . '</table></div>'
                . '<p style="margin:0;font-size:13px;color:#9ca3af">如有疑问，欢迎直接回复此邮件与我们联系。</p>';
        }

        $fullHtml = \AstraHub_Mailer::buildEmailHtml($contentHtml, $siteName, $siteUrl, '友链审核结果');

        $options = array(
            'driver' => isset($fa['mailDriver']) ? (string) $fa['mailDriver'] : 'phpmail',
            'from_email' => $adminEmail !== '' ? $adminEmail : 'noreply@example.com',
            'from_name' => $siteName,
            'reply_to' => '',
            'smtp_host' => isset($fa['smtpHost']) ? (string) $fa['smtpHost'] : '',
            'smtp_port' => isset($fa['smtpPort']) ? (int) $fa['smtpPort'] : 587,
            'smtp_user' => isset($fa['smtpUser']) ? (string) $fa['smtpUser'] : '',
            'smtp_pass' => isset($fa['smtpPass']) ? (string) $fa['smtpPass'] : '',
            'smtp_secure' => isset($fa['smtpSecure']) ? (string) $fa['smtpSecure'] : 'tls',
        );

        return \AstraHub_Mailer::send($applicantEmail, $subject, $fullHtml, $options);
    }

    private function handlePublicStatus()
    {
        $cred = \AstraHub_CredentialStore::credentials();
        if ($cred['siteId'] === '' || $cred['apiKey'] === '') {
            $this->json(array('events' => array()));
            return;
        }
        $s = \AstraHub_CredentialStore::load();
        $base = isset($s['connection']['hubBaseUrl']) && $s['connection']['hubBaseUrl'] !== ''
            ? $s['connection']['hubBaseUrl'] : 'https://astra.aobp.cn';
        $client = new \AstraHub_HubClient($base, 15);
        $resp = $client->signedRequest('GET', '/v1/planet/broadcasts?limit=20&hours=24', null, $cred['siteId'], $cred['apiKey']);
        $events = array();
        if ($resp['ok'] && is_array($resp['json']) && isset($resp['json']['items']) && is_array($resp['json']['items'])) {
            foreach ($resp['json']['items'] as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $id = isset($item['id']) ? (string) $item['id'] : '';
                $itemType = isset($item['type']) ? (string) $item['type'] : '';
                $title = isset($item['title']) ? (string) $item['title'] : '';
                $message = isset($item['message']) ? (string) $item['message'] : '';
                $summary = isset($item['summary']) ? (string) $item['summary'] : '';
                $level = isset($item['level']) ? (string) $item['level'] : 'info';
                $siteName = isset($item['siteName']) ? (string) $item['siteName'] : '';
                $nodeName = isset($item['nodeName']) ? (string) $item['nodeName'] : '';
                $nodeAvatar = isset($item['avatar']) ? (string) $item['avatar'] : '';
                $url = isset($item['url']) ? (string) $item['url'] : '';
                $time = isset($item['time']) ? (string) $item['time'] : '';

                if ($itemType === 'rss' && $url !== '' && $title !== '') {
                    $events[] = array(
                        'type' => 'mascot_article_card',
                        'id' => $id,
                        'level' => $level,
                        'title' => $title,
                        'message' => $message,
                        'siteName' => $siteName,
                        'nodeName' => $nodeName,
                        'nodeAvatar' => $nodeAvatar,
                        'time' => $time,
                        'article' => array(
                            'id' => $id,
                            'title' => $title,
                            'url' => $url,
                            'summary' => $summary,
                            'publishedAt' => $time,
                            'nodeName' => $nodeName,
                            'nodeAvatar' => $nodeAvatar,
                        ),
                    );
                    continue;
                }

                $events[] = array(
                    'type' => 'mascot_bubble',
                    'id' => $id,
                    'level' => $level,
                    'title' => $title,
                    'message' => $message,
                    'siteName' => $siteName,
                    'nodeName' => $nodeName,
                    'nodeAvatar' => $nodeAvatar,
                    'time' => $time,
                );
            }
        }
        $this->json(array(
            'healthy' => true,
            'statusLabel' => '主星已链接',
            'events' => $events,
        ));
    }

    private function handleWsToken()
    {
        $cred = \AstraHub_CredentialStore::credentials();
        if ($cred['siteId'] === '' || $cred['apiKey'] === '') {
            $this->json(array('success' => false, 'message' => '站点未接入，无法获取实时令牌'), 400);
            return;
        }
        $s = \AstraHub_CredentialStore::load();
        $base = isset($s['connection']['hubBaseUrl']) && $s['connection']['hubBaseUrl'] !== ''
            ? $s['connection']['hubBaseUrl']
            : 'https://astra.aobp.cn';

        $client = new \AstraHub_HubClient($base, 15);
        $resp = $client->signedRequest('POST', '/v1/ws-token', null, $cred['siteId'], $cred['apiKey']);
        $json = is_array($resp['json']) ? $resp['json'] : array();

        $token = isset($json['token']) ? (string) $json['token'] : '';
        $ok = $resp['ok'] && $token !== '';
        $this->json(array(
            'success' => $ok,
            'status' => $resp['status'],
            'token' => $token,
            'expiresAt' => isset($json['expiresAt']) ? (string) $json['expiresAt'] : '',
        ), $ok ? 200 : 400);
    }

    private function hubMessage($json, $resp)
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
        return '获取实时连接令牌失败：HTTP ' . $resp['status'];
    }

    private function persistConnectionFromBody(array $body, array $result)
    {
        $nodeName = $result['nodeName'] !== '' ? $result['nodeName'] : ($result['category'] !== '' ? $result['category'] : $this->field($body, 'siteNodeName'));
        $nodeAvatar = $result['nodeAvatar'] !== '' ? $result['nodeAvatar'] : $this->field($body, 'siteNodeAvatar');
        \AstraHub_CredentialStore::save(array('connection' => array(
            'hubBaseUrl' => $this->hubBase($body),
            'siteName' => $this->field($body, 'siteName'),
            'siteUrl' => $this->field($body, 'siteUrl'),
            'siteDescription' => $this->field($body, 'siteDescription'),
            'siteRssUrl' => $this->field($body, 'siteRssUrl'),
            'contactEmail' => $this->field($body, 'contactEmail'),
            'siteNodeName' => $nodeName,
            'siteNodeAvatar' => $nodeAvatar,
        )));
    }

    private function hubBase($body)
    {
        $fromBody = $this->field($body, 'hubBaseUrl');
        if ($fromBody !== '') {
            return $fromBody;
        }
        $s = \AstraHub_CredentialStore::load();
        $stored = isset($s['connection']['hubBaseUrl']) ? $s['connection']['hubBaseUrl'] : '';
        return $stored !== '' ? $stored : 'https://astra.aobp.cn';
    }

    private function readJsonBody()
    {
        $raw = file_get_contents('php://input');
        if ($raw === false || $raw === '') {
            return array();
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : array();
    }

    private function field($body, $key)
    {
        return (is_array($body) && isset($body[$key])) ? trim((string) $body[$key]) : '';
    }

    private static function maskKey($key)
    {
        if ($key === '') {
            return '';
        }
        if (strlen($key) <= 8) {
            return '********';
        }
        return substr($key, 0, 4) . '********' . substr($key, -4);
    }

    private function json($data, $status = 200)
    {
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    private function rawError($status, $message)
    {
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: text/plain; charset=utf-8');
        }
        echo (string) $message;
        exit;
    }
}
