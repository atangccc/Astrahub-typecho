<?php
/**
 * AstraHub 邮件发送服务。
 */

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class AstraHub_Mailer
{
    /**
     * @param string $to
     * @param string $subject
     * @param string $htmlBody
     * @param array $options
     * @return array{ok:bool,error:string}
     */
    public static function send($to, $subject, $htmlBody, array $options = array())
    {
        $driver = isset($options['driver']) ? strtolower(trim((string) $options['driver'])) : 'phpmail';

        if ($driver === 'smtp') {
            return self::sendSmtp($to, $subject, $htmlBody, $options);
        }

        return self::sendPhpMail($to, $subject, $htmlBody, $options);
    }

    private static function sendPhpMail($to, $subject, $htmlBody, array $options)
    {
        $to = self::sanitizeHeader($to);
        $subject = self::sanitizeHeader($subject);

        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return array('ok' => false, 'error' => 'invalid recipient');
        }
        if ($subject === '') {
            return array('ok' => false, 'error' => 'empty subject');
        }

        $fromEmail = isset($options['from_email']) ? self::sanitizeHeader($options['from_email']) : '';
        $fromName = isset($options['from_name']) ? self::sanitizeHeader($options['from_name']) : '';
        $replyTo = isset($options['reply_to']) ? self::sanitizeHeader($options['reply_to']) : '';

        if ($fromEmail !== '' && !filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
            $fromEmail = '';
        }
        if ($replyTo !== '' && !filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
            $replyTo = '';
        }

        $headers = array();
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-Type: text/html; charset=UTF-8';

        if ($fromEmail !== '') {
            if ($fromName !== '') {
                $encodedName = '=?UTF-8?B?' . base64_encode($fromName) . '?=';
                $headers[] = 'From: ' . $encodedName . ' <' . $fromEmail . '>';
            } else {
                $headers[] = 'From: ' . $fromEmail;
            }
        }

        if ($replyTo !== '') {
            $headers[] = 'Reply-To: ' . $replyTo;
        }

        $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';

        $ok = @mail($to, $encodedSubject, $htmlBody, implode("\r\n", $headers));
        if (!$ok) {
            return array('ok' => false, 'error' => 'mail() failed');
        }

        return array('ok' => true, 'error' => '');
    }

    private static function sendSmtp($to, $subject, $htmlBody, array $options)
    {
        $to = self::sanitizeHeader($to);
        $subject = self::sanitizeHeader($subject);

        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return array('ok' => false, 'error' => 'invalid recipient');
        }

        $host = isset($options['smtp_host']) ? trim((string) $options['smtp_host']) : '';
        $port = isset($options['smtp_port']) ? (int) $options['smtp_port'] : 587;
        $user = isset($options['smtp_user']) ? trim((string) $options['smtp_user']) : '';
        $pass = isset($options['smtp_pass']) ? (string) $options['smtp_pass'] : '';
        $secure = isset($options['smtp_secure']) ? strtolower(trim((string) $options['smtp_secure'])) : 'tls';

        $fromEmail = isset($options['from_email']) ? self::sanitizeHeader($options['from_email']) : '';
        $fromName = isset($options['from_name']) ? self::sanitizeHeader($options['from_name']) : 'AstraHub';
        $replyTo = isset($options['reply_to']) ? self::sanitizeHeader($options['reply_to']) : '';

        if ($host === '') {
            return array('ok' => false, 'error' => 'smtp_host required');
        }
        if ($fromEmail === '' && $user !== '' && filter_var($user, FILTER_VALIDATE_EMAIL)) {
            $fromEmail = $user;
        }
        if ($fromEmail === '' || !filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
            return array('ok' => false, 'error' => 'from_email required');
        }

        $prefix = '';
        if ($secure === 'ssl') {
            $prefix = 'ssl://';
        }

        $errno = 0;
        $errstr = '';
        $socket = @fsockopen($prefix . $host, $port, $errno, $errstr, 15);
        if (!$socket) {
            return array('ok' => false, 'error' => 'connection failed: ' . $errstr);
        }

        stream_set_timeout($socket, 30);

        $response = self::smtpRead($socket);
        if (strpos($response, '220') !== 0) {
            fclose($socket);
            return array('ok' => false, 'error' => 'unexpected greeting: ' . $response);
        }

        self::smtpWrite($socket, 'EHLO localhost');
        $response = self::smtpRead($socket);

        if ($secure === 'tls' && $prefix === '') {
            self::smtpWrite($socket, 'STARTTLS');
            $response = self::smtpRead($socket);
            if (strpos($response, '220') !== 0) {
                fclose($socket);
                return array('ok' => false, 'error' => 'STARTTLS failed: ' . $response);
            }
            $crypto = stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            if (!$crypto) {
                fclose($socket);
                return array('ok' => false, 'error' => 'TLS handshake failed');
            }
            self::smtpWrite($socket, 'EHLO localhost');
            $response = self::smtpRead($socket);
        }

        if ($user !== '' && $pass !== '') {
            self::smtpWrite($socket, 'AUTH LOGIN');
            $response = self::smtpRead($socket);
            if (strpos($response, '334') !== 0) {
                fclose($socket);
                return array('ok' => false, 'error' => 'AUTH LOGIN failed: ' . $response);
            }

            self::smtpWrite($socket, base64_encode($user));
            $response = self::smtpRead($socket);
            if (strpos($response, '334') !== 0) {
                fclose($socket);
                return array('ok' => false, 'error' => 'AUTH username failed: ' . $response);
            }

            self::smtpWrite($socket, base64_encode($pass));
            $response = self::smtpRead($socket);
            if (strpos($response, '235') !== 0) {
                fclose($socket);
                return array('ok' => false, 'error' => 'AUTH password failed: ' . $response);
            }
        }

        self::smtpWrite($socket, 'MAIL FROM:<' . $fromEmail . '>');
        $response = self::smtpRead($socket);
        if (strpos($response, '250') !== 0) {
            fclose($socket);
            return array('ok' => false, 'error' => 'MAIL FROM failed: ' . $response);
        }

        self::smtpWrite($socket, 'RCPT TO:<' . $to . '>');
        $response = self::smtpRead($socket);
        if (strpos($response, '250') !== 0) {
            fclose($socket);
            return array('ok' => false, 'error' => 'RCPT TO failed: ' . $response);
        }

        self::smtpWrite($socket, 'DATA');
        $response = self::smtpRead($socket);
        if (strpos($response, '354') !== 0) {
            fclose($socket);
            return array('ok' => false, 'error' => 'DATA failed: ' . $response);
        }

        $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        $encodedFromName = '=?UTF-8?B?' . base64_encode($fromName) . '?=';
        $boundary = '----=_Part_' . md5(uniqid(time()));

        $message = 'Date: ' . date('r') . "\r\n";
        $message .= 'From: ' . $encodedFromName . ' <' . $fromEmail . ">\r\n";
        $message .= 'To: ' . $to . "\r\n";
        if ($replyTo !== '' && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
            $message .= 'Reply-To: ' . $replyTo . "\r\n";
        }
        $message .= 'Subject: ' . $encodedSubject . "\r\n";
        $message .= 'MIME-Version: 1.0' . "\r\n";
        $message .= 'Content-Type: text/html; charset=UTF-8' . "\r\n";
        $message .= 'Content-Transfer-Encoding: base64' . "\r\n";
        $message .= "\r\n";
        $message .= chunk_split(base64_encode($htmlBody), 76, "\r\n");
        $message .= "\r\n.";

        self::smtpWrite($socket, $message);
        $response = self::smtpRead($socket);
        if (strpos($response, '250') !== 0) {
            fclose($socket);
            return array('ok' => false, 'error' => 'message send failed: ' . $response);
        }

        self::smtpWrite($socket, 'QUIT');
        fclose($socket);

        return array('ok' => true, 'error' => '');
    }

    private static function smtpWrite($socket, $data)
    {
        fwrite($socket, $data . "\r\n");
    }

    private static function smtpRead($socket)
    {
        $response = '';
        while ($line = fgets($socket, 515)) {
            $response .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        return trim($response);
    }

    private static function sanitizeHeader($value)
    {
        $value = str_replace(array("\r", "\n"), '', (string) $value);
        return trim($value);
    }

    /**
     * @param string $contentHtml
     * @param string $siteName
     * @param string $siteUrl
     * @param string $subtitle
     * @return string
     */
    public static function buildEmailHtml($contentHtml, $siteName, $siteUrl, $subtitle = '')
    {
        if ($subtitle === '') {
            $subtitle = '友链通知';
        }
        $sn = htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8');
        $su = htmlspecialchars($siteUrl, ENT_QUOTES, 'UTF-8');
        $st = htmlspecialchars($subtitle, ENT_QUOTES, 'UTF-8');

        return '<!DOCTYPE html>'
            . '<html lang="zh-CN"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>'
            . '<body style="margin:0;padding:0;background:#f0f4f8;-webkit-text-size-adjust:100%">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0"'
            . ' style="background:#f0f4f8;padding:32px 16px;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,\'Helvetica Neue\',sans-serif">'
            . '<tr><td align="center">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px">'
            . '<tr><td style="background:linear-gradient(135deg,#0061a4 0%,#4a7cc9 100%);border-radius:16px 16px 0 0;padding:24px 32px">'
            . '<div style="color:#ffffff;font-size:20px;font-weight:700;line-height:1.3">' . $sn . '</div>'
            . '<div style="color:rgba(255,255,255,.7);font-size:12px;margin-top:4px;letter-spacing:.3px">' . $st . '</div>'
            . '</td></tr>'
            . '<tr><td style="background:#ffffff;padding:28px 32px;border-left:1px solid #e5e7eb;border-right:1px solid #e5e7eb">'
            . $contentHtml
            . '</td></tr>'
            . '<tr><td style="background:#f5f7fa;border:1px solid #e5e7eb;border-top:none;border-radius:0 0 16px 16px;padding:14px 32px;text-align:center">'
            . '<div style="font-size:11px;color:#9ca3af">'
            . '此邮件由 <a href="' . $su . '" style="color:#0061a4;text-decoration:none">' . $sn . '</a> 自动发送 &middot; Powered by AstraHub'
            . '</div>'
            . '</td></tr>'
            . '</table></td></tr></table>'
            . '</body></html>';
    }
}
