<?php
/**
 * AstraHub HTTP 客户端。
 */

if (!class_exists('AstraHub_HubSigner')) {
    require_once __DIR__ . '/HubSigner.php';
}

class AstraHub_HubClient
{
    /** @var string */
    private $baseUrl;
    /** @var int */
    private $timeout;

    public function __construct($baseUrl, $timeout = 15)
    {
        $this->baseUrl = rtrim((string) $baseUrl, '/');
        $this->timeout = (int) $timeout;
    }

    /**
     * @param string $method
     * @param string $path
     * @param array|null $payload
     * @param string $siteId
     * @param string $apiKey
     * @param array  $extraHeaders
     * @param array  $signOpts
     * @return array
     */
    public function signedRequest($method, $path, $payload, $siteId, $apiKey, array $extraHeaders = array(), array $signOpts = array())
    {
        $method = strtoupper($method);

        $body = '';
        if ($payload !== null) {
            $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if ($body === false) {
                return $this->errorResult('payload json_encode failed');
            }
        }

        $signHeaders = AstraHub_HubSigner::sign($method, $path, $body, $siteId, $apiKey, $signOpts);

        $headers = array(
            'Content-Type: application/json',
            'Accept: application/json',
            'X-BP-Site-Id: ' . $signHeaders['X-BP-Site-Id'],
            'X-BP-Timestamp: ' . $signHeaders['X-BP-Timestamp'],
            'X-BP-Nonce: ' . $signHeaders['X-BP-Nonce'],
            'X-BP-Signature: ' . $signHeaders['X-BP-Signature'],
        );
        foreach ($extraHeaders as $k => $v) {
            $headers[] = $k . ': ' . $v;
        }

        return $this->raw($method, $this->baseUrl . $path, $headers, $body);
    }

    public function unsignedRequest($method, $path, $payload, array $extraHeaders = array())
    {
        $method = strtoupper($method);
        $body = '';
        if ($payload !== null) {
            $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if ($body === false) {
                return $this->errorResult('payload json_encode failed');
            }
        }
        $headers = array(
            'Content-Type: application/json',
            'Accept: application/json',
        );
        foreach ($extraHeaders as $k => $v) {
            $headers[] = $k . ': ' . $v;
        }
        return $this->raw($method, $this->baseUrl . $path, $headers, $body);
    }

    private function raw($method, $url, array $headers, $body)
    {
        if (function_exists('curl_init')) {
            return $this->viaCurl($method, $url, $headers, $body);
        }
        return $this->viaStream($method, $url, $headers, $body);
    }

    private function viaCurl($method, $url, array $headers, $body)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        if ($method === 'POST' || $body !== '') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        $resp = curl_exec($ch);
        if ($resp === false) {
            $err = curl_error($ch);
            curl_close($ch);
            return $this->errorResult('curl error: ' . $err);
        }
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return $this->buildResult($status, $resp);
    }

    private function viaStream($method, $url, array $headers, $body)
    {
        $context = stream_context_create(array(
            'http' => array(
                'method' => $method,
                'header' => implode("\r\n", $headers),
                'content' => $body,
                'timeout' => $this->timeout,
                'ignore_errors' => true,
            ),
            'ssl' => array(
                'verify_peer' => true,
                'verify_peer_name' => true,
            ),
        ));
        $resp = @file_get_contents($url, false, $context);
        if ($resp === false) {
            return $this->errorResult('stream request failed (allow_url_fopen?)');
        }
        $status = 0;
        if (isset($http_response_header) && is_array($http_response_header)) {
            foreach ($http_response_header as $line) {
                if (preg_match('#^HTTP/\S+\s+(\d{3})#', $line, $m)) {
                    $status = (int) $m[1];
                }
            }
        }
        return $this->buildResult($status, $resp);
    }

    private function buildResult($status, $raw)
    {
        $json = null;
        if ($raw !== '' && $raw !== null) {
            $decoded = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $json = $decoded;
            }
        }
        return array(
            'status' => $status,
            'ok' => ($status >= 200 && $status < 300),
            'body' => $raw,
            'json' => $json,
            'error' => '',
        );
    }

    private function errorResult($message)
    {
        return array(
            'status' => 0,
            'ok' => false,
            'body' => '',
            'json' => null,
            'error' => (string) $message,
        );
    }
}
