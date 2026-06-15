<?php
/**
 * AstraHub HMAC-SHA256 v1 签名器。
 */

if (!defined('ASTRAHUB_EMPTY_BODY_SHA256')) {
    define('ASTRAHUB_EMPTY_BODY_SHA256', 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855');
}

class AstraHub_HubSigner
{
    /**
     * @param string $method
     * @param string $path
     * @param string $body
     * @param string $siteId
     * @param string $apiKey
     * @param array  $opts
     * @return array{X-BP-Site-Id:string,X-BP-Timestamp:string,X-BP-Nonce:string,X-BP-Signature:string}
     */
    public static function sign($method, $path, $body, $siteId, $apiKey, array $opts = array())
    {
        $timestamp = isset($opts['timestamp'])
            ? (string) $opts['timestamp']
            : (string) time();
        $nonce = isset($opts['nonce'])
            ? (string) $opts['nonce']
            : self::generateNonce();

        $signature = self::computeSignature($method, $path, $body, $apiKey, $timestamp, $nonce);

        return array(
            'X-BP-Site-Id' => (string) $siteId,
            'X-BP-Timestamp' => $timestamp,
            'X-BP-Nonce' => $nonce,
            'X-BP-Signature' => $signature,
        );
    }

    public static function computeSignature($method, $path, $body, $apiKey, $timestamp, $nonce)
    {
        $canonical = self::buildCanonical($method, $path, $body, $timestamp, $nonce);
        return hash_hmac('sha256', $canonical, (string) $apiKey);
    }

    public static function buildCanonical($method, $path, $body, $timestamp, $nonce)
    {
        $methodUpper = strtoupper((string) $method);
        $pathOnly = self::stripQuery((string) $path);
        $bodyHash = self::bodyHash((string) $body);

        return $methodUpper . "\n"
            . $pathOnly . "\n"
            . (string) $timestamp . "\n"
            . (string) $nonce . "\n"
            . $bodyHash;
    }

    public static function bodyHash($body)
    {
        if ($body === '' || $body === null) {
            return ASTRAHUB_EMPTY_BODY_SHA256;
        }
        return hash('sha256', (string) $body);
    }

    public static function stripQuery($path)
    {
        $path = (string) $path;
        if (preg_match('#^https?://#i', $path)) {
            $parsed = parse_url($path);
            $path = isset($parsed['path']) ? $parsed['path'] : '/';
        }
        $qpos = strpos($path, '?');
        if ($qpos !== false) {
            $path = substr($path, 0, $qpos);
        }
        $hpos = strpos($path, '#');
        if ($hpos !== false) {
            $path = substr($path, 0, $hpos);
        }
        if ($path === '') {
            $path = '/';
        }
        return $path;
    }

    public static function generateNonce()
    {
        if (function_exists('random_bytes')) {
            try {
                return bin2hex(random_bytes(16));
            } catch (\Exception $e) {
            }
        }
        if (function_exists('openssl_random_pseudo_bytes')) {
            $bytes = openssl_random_pseudo_bytes(16, $strong);
            if ($bytes !== false) {
                return bin2hex($bytes);
            }
        }
        $hex = '';
        for ($i = 0; $i < 32; $i++) {
            $hex .= dechex(mt_rand(0, 15));
        }
        return $hex;
    }
}
