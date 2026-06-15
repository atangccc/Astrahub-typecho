<?php
/**
 * AstraHub 设置与凭证存取。
 */

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class AstraHub_CredentialStore
{
    const OPTION_NAME = 'astrahub';

    public static function load()
    {
        $raw = self::readRawOption();
        $data = array();
        if ($raw !== null && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $data = $decoded;
            }
        }
        $settings = self::mergeDefaults($data);

        if (!empty($settings['credentials']['apiKey'])) {
            $settings['credentials']['apiKey'] = self::decrypt($settings['credentials']['apiKey']);
        }
        return $settings;
    }

    public static function save(array $settings)
    {
        $current = self::load();
        $merged = array_replace_recursive($current, $settings);

        $toStore = $merged;
        if (!empty($toStore['credentials']['apiKey'])) {
            $toStore['credentials']['apiKey'] = self::encrypt($toStore['credentials']['apiKey']);
        }

        $json = json_encode($toStore, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        self::writeRawOption($json);
        return true;
    }

    public static function saveCredentials($siteId, $apiKey, $createdAt)
    {
        return self::save(array(
            'credentials' => array(
                'siteId' => (string) $siteId,
                'apiKey' => (string) $apiKey,
                'createdAt' => (string) $createdAt,
            ),
        ));
    }

    public static function credentials()
    {
        $s = self::load();
        return array(
            'siteId' => isset($s['credentials']['siteId']) ? $s['credentials']['siteId'] : '',
            'apiKey' => isset($s['credentials']['apiKey']) ? $s['credentials']['apiKey'] : '',
        );
    }

    public static function cronToken()
    {
        $cred = self::credentials();
        return hash('sha256', 'astrahub-cron|' . self::cryptoSeed() . '|' . $cred['siteId']);
    }

    private static function cryptoSeed()
    {
        $seed = '';
        if (defined('__TYPECHO_SECRET__') && __TYPECHO_SECRET__) {
            $seed = __TYPECHO_SECRET__;
        }
        if ($seed === '') {
            try {
                $options = \Widget\Options::alloc();
                if (isset($options->secret) && $options->secret) {
                    $seed = $options->secret;
                }
            } catch (\Exception $e) {
            }
        }
        if ($seed === '') {
            $db = \Typecho\Db::get();
            $seed = 'astrahub|' . $db->getPrefix();
        }
        return $seed;
    }

    private static function readRawOption()
    {
        $db = \Typecho\Db::get();
        $prefix = $db->getPrefix();
        $row = $db->fetchRow(
            $db->select('value')->from($prefix . 'options')
                ->where('name = ?', self::OPTION_NAME)
                ->where('user = ?', 0)
                ->limit(1)
        );
        return $row ? $row['value'] : null;
    }

    private static function writeRawOption($value)
    {
        $db = \Typecho\Db::get();
        $prefix = $db->getPrefix();
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

    private static function cryptoKey()
    {
        return hash('sha256', 'astrahub-cred|' . self::cryptoSeed(), true);
    }

    private static function encrypt($plain)
    {
        if ($plain === '' || $plain === null) {
            return '';
        }
        if (!function_exists('openssl_encrypt')) {
            return 'plain:' . $plain;
        }
        $key = self::cryptoKey();
        $iv = openssl_random_pseudo_bytes(16);
        $cipher = openssl_encrypt($plain, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        if ($cipher === false) {
            return 'plain:' . $plain;
        }
        return 'enc:' . base64_encode($iv . $cipher);
    }

    private static function decrypt($stored)
    {
        if ($stored === '' || $stored === null) {
            return '';
        }
        if (strpos($stored, 'plain:') === 0) {
            return substr($stored, 6);
        }
        if (strpos($stored, 'enc:') !== 0) {
            return $stored;
        }
        if (!function_exists('openssl_decrypt')) {
            return '';
        }
        $blob = base64_decode(substr($stored, 4));
        if ($blob === false || strlen($blob) <= 16) {
            return '';
        }
        $iv = substr($blob, 0, 16);
        $cipher = substr($blob, 16);
        $key = self::cryptoKey();
        $plain = openssl_decrypt($cipher, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        return $plain === false ? '' : $plain;
    }

    public static function defaults()
    {
        return array(
            'connection' => array(
                'hubBaseUrl' => 'https://astra.aobp.cn',
                'registerToken' => '',
                'siteName' => '',
                'siteUrl' => '',
                'siteDescription' => '',
                'contactEmail' => '',
                'siteNodeName' => '',
                'siteNodeAvatar' => '',
                'siteRssUrl' => '',
            ),
            'credentials' => array(
                'siteId' => '',
                'apiKey' => '',
                'createdAt' => '',
            ),
            'invitation' => array(
                'allowIncomingInvitations' => true,
                'allowOutgoingInvitations' => true,
            ),
            'widget' => array('enabled' => true),
            'realtimeBroadcast' => array('enabled' => true),
            'favorites' => array('pinnedSiteUrls' => array()),
            'readLater' => array('items' => array()),
            'friendApply' => array(
                'enabled' => true,
                'notifyEmail' => '',
                'mailDriver' => 'phpmail',
                'smtpHost' => '',
                'smtpPort' => 587,
                'smtpUser' => '',
                'smtpPass' => '',
                'smtpSecure' => 'tls',
            ),
            'friendApplyRateLimit' => array(),
        );
    }

    private static function mergeDefaults(array $data)
    {
        return array_replace_recursive(self::defaults(), $data);
    }
}
