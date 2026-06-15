<?php
/**
 * AstraHub 注册与登舱恢复服务。
 */

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

if (!class_exists('AstraHub_HubClient')) {
    require_once __DIR__ . '/HubClient.php';
}

class AstraHub_RegisterService
{
    const PLUGIN_NAME = 'plugin-typecho-astrahub';
    const PLUGIN_VERSION = '0.1.0';

    private $client;

    public function __construct($hubBaseUrl)
    {
        $this->client = new AstraHub_HubClient($hubBaseUrl, 20);
    }

    /**
     * @return array{success:bool,status:int,message:string,expiresAt:string}
     */
    public function requestInvitationCode($contactEmail, $siteUrl)
    {
        $resp = $this->client->unsignedRequest('POST', '/v1/sites/invitations/apply', array(
            'contactEmail' => (string) $contactEmail,
            'siteUrl' => (string) $siteUrl,
        ));
        $json = is_array($resp['json']) ? $resp['json'] : array();
        return array(
            'success' => $resp['ok'],
            'status' => $resp['status'],
            'message' => $this->pickMessage($json, $resp),
            'expiresAt' => isset($json['expiresAt']) ? (string) $json['expiresAt'] : '',
        );
    }

    /**
     * @param array $payload
     * @return array
     */
    public function registerWithInvitation(array $payload)
    {
        $invitationCode = isset($payload['invitationCode']) ? trim($payload['invitationCode']) : '';
        if ($invitationCode === '') {
            return $this->failure('缺少签发码');
        }

        $body = $this->buildRegisterBody($payload);
        $resp = $this->client->unsignedRequest(
            'POST',
            '/v1/sites/register',
            $body,
            array('X-BP-Invitation-Code' => $invitationCode)
        );
        return $this->parseRegisterResponse($resp);
    }

    public function sendBoardingCode($contactEmail)
    {
        $resp = $this->client->unsignedRequest('POST', '/v1/sites/boarding/send-code', array(
            'contactEmail' => (string) $contactEmail,
        ));
        $json = is_array($resp['json']) ? $resp['json'] : array();
        return array(
            'success' => $resp['ok'],
            'status' => $resp['status'],
            'message' => $this->pickMessage($json, $resp),
            'expiresAt' => isset($json['expiresAt']) ? (string) $json['expiresAt'] : '',
        );
    }

    public function restoreByBoardingCode($contactEmail, $code)
    {
        $resp = $this->client->unsignedRequest('POST', '/v1/sites/boarding/restore', array(
            'contactEmail' => (string) $contactEmail,
            'code' => (string) $code,
        ));
        $json = is_array($resp['json']) ? $resp['json'] : array();
        $ok = $resp['ok'] && !empty($json['siteId']) && !empty($json['apiKey']);
        return array(
            'success' => $ok,
            'status' => $resp['status'],
            'message' => $this->pickMessage($json, $resp),
            'siteId' => $this->str($json, 'siteId'),
            'apiKey' => $this->str($json, 'apiKey'),
            'siteName' => $this->str($json, 'siteName'),
            'siteUrl' => $this->str($json, 'siteUrl'),
            'contactEmail' => $this->str($json, 'contactEmail'),
            'description' => $this->str($json, 'description'),
            'rssUrl' => $this->str($json, 'rssUrl'),
            'nodeName' => $this->str($json, 'nodeName') !== '' ? $this->str($json, 'nodeName') : $this->str($json, 'category'),
            'category' => $this->str($json, 'category'),
            'nodeAvatar' => $this->str($json, 'nodeAvatar'),
            'createdAt' => $this->str($json, 'createdAt'),
        );
    }

    private function buildRegisterBody(array $p)
    {
        return array(
            'name' => $this->p($p, 'siteName'),
            'url' => $this->p($p, 'siteUrl'),
            'description' => $this->p($p, 'siteDescription'),
            'rssUrl' => $this->p($p, 'siteRssUrl'),
            'avatarUrl' => $this->p($p, 'siteAvatarUrl'),
            'contactEmail' => $this->p($p, 'contactEmail'),
            'nodeName' => $this->p($p, 'siteNodeName'),
            'category' => $this->p($p, 'siteNodeName'),
            'nodeAvatar' => $this->p($p, 'siteNodeAvatar'),
        );
    }

    private function parseRegisterResponse($resp)
    {
        $json = is_array($resp['json']) ? $resp['json'] : array();
        $ok = $resp['ok'] && !empty($json['siteId']) && !empty($json['apiKey']);
        return array(
            'success' => $ok,
            'status' => $resp['status'],
            'message' => $this->pickMessage($json, $resp),
            'siteId' => $this->str($json, 'siteId'),
            'apiKey' => $this->str($json, 'apiKey'),
            'createdAt' => $this->str($json, 'createdAt'),
            'nodeName' => $this->str($json, 'nodeName'),
            'category' => $this->str($json, 'category'),
            'nodeAvatar' => $this->str($json, 'nodeAvatar'),
        );
    }

    private function pickMessage($json, $resp)
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
        return $resp['ok'] ? 'ok' : ('请求失败：HTTP ' . $resp['status']);
    }

    private function failure($message)
    {
        return array('success' => false, 'status' => 0, 'message' => $message);
    }

    private function p(array $p, $key)
    {
        return isset($p[$key]) ? (string) $p[$key] : '';
    }

    private function str($json, $key)
    {
        return isset($json[$key]) ? (string) $json[$key] : '';
    }
}
