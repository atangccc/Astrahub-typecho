<?php

namespace TypechoPlugin\AstraHub;

use Typecho\Plugin\PluginInterface;
use Typecho\Plugin\Exception as PluginException;
use Typecho\Widget\Helper\Form;
use Typecho\Db;
use Utils\Helper;

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * AstraHub 星链 · 连接独立博客的跨生态聚合网络。
 *
 * 打破不同博客系统之间的孤岛：让各类生态的站点接入同一张关系网络，
 * 在线交换友链、同步身份与公开动态，沿节点关系发现同好，共同构建一个不断生长的博客宇宙。
 *
 * @package AstraHub
 * @author AstraHub
 * @version 1.1.2
 * @link https://astra.aobp.cn
 */
class Plugin implements PluginInterface
{
    public static function activate()
    {
        $info = self::installTables();

        Helper::addPanel(1, 'AstraHub/panel.php', 'AstraHub 星链', 'AstraHub 星链接入与友链管理', 'administrator');
        Helper::addAction('astrahub', '\\TypechoPlugin\\AstraHub\\Action');
        \Widget\Archive::pluginHandle()->footer = __CLASS__ . '::onFrontFooter';

        return _t($info);
    }

    public static function deactivate()
    {
        Helper::removeAction('astrahub');
        Helper::removePanel(1, 'AstraHub/panel.php');
    }

    public static function config(Form $form)
    {
    }

    public static function personalConfig(Form $form)
    {
    }

    public static function onFrontFooter()
    {
        require_once dirname(__FILE__) . '/lib/CredentialStore.php';
        require_once dirname(__FILE__) . '/lib/Cron.php';

        try {
            \AstraHub_Cron::tick(false);
        } catch (\Exception $e) {
            // 兜底推送失败不影响前台渲染
        }

        $settings = \AstraHub_CredentialStore::load();
        $cred = isset($settings['credentials']) ? $settings['credentials'] : array();
        $conn = isset($settings['connection']) ? $settings['connection'] : array();
        $widget = isset($settings['widget']) ? $settings['widget'] : array();

        $linked = !empty($cred['siteId']) && !empty($cred['apiKey']);
        $widgetEnabled = !isset($widget['enabled']) || $widget['enabled'];
        if (!$linked || !$widgetEnabled) {
            return;
        }

        $options = \Widget\Options::alloc();
        $pluginUrl = rtrim($options->pluginUrl, '/') . '/AstraHub';

        $data = array(
            'linked' => true,
            'siteName' => isset($conn['siteName']) ? $conn['siteName'] : '',
            'siteUrl' => isset($conn['siteUrl']) ? $conn['siteUrl'] : '',
            'nodeName' => isset($conn['siteNodeName']) ? $conn['siteNodeName'] : '',
            'nodeAvatar' => isset($conn['siteNodeAvatar']) ? $conn['siteNodeAvatar'] : '',
            'hubBaseUrl' => isset($conn['hubBaseUrl']) ? $conn['hubBaseUrl'] : 'https://astra.aobp.cn',
            'joinUrl' => isset($conn['hubBaseUrl']) ? $conn['hubBaseUrl'] : 'https://astra.aobp.cn',
            'protocol' => 'GALAXY-X9',
            'healthy' => true,
            'statusLabel' => '主星已链接',
            'creators' => array(),
            'moreCreatorCount' => 0,
            'realtimeBroadcast' => array('enabled' => isset($settings['realtimeBroadcast']['enabled']) ? (bool) $settings['realtimeBroadcast']['enabled'] : true),
        );

        $pollUrl = \Typecho\Common::url('/action/astrahub?do=publicStatus', $options->index);
        $widgetCfg = array(
            'staticBase' => $pluginUrl . '/static',
            'pollUrl' => $pollUrl,
            'pollIntervalMs' => 25000,
        );

        echo '<link rel="stylesheet" href="' . htmlspecialchars($pluginUrl) . '/widget/galaxy-link-widget.css">' . "\n";
        echo '<script>window.__ASTRAHUB_WIDGET__=' . json_encode($widgetCfg, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . ';</script>' . "\n";
        echo '<script type="application/json" id="astrahub-galaxy-widget-data">'
            . json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>' . "\n";
        echo '<script src="' . htmlspecialchars($pluginUrl) . '/widget/galaxy-link-widget.js"></script>' . "\n";
    }

    private static function installTables()
    {
        $db = Db::get();
        $adapter = $db->getAdapterName();
        $segments = explode('_', $adapter);
        $type = strtolower(array_pop($segments));
        $prefix = $db->getPrefix();

        if ($type === 'mysql' || $type === 'mysqli' || strpos($adapter, 'Mysql') !== false || strpos($adapter, 'Mysqli') !== false) {
            $sqlFile = 'mysql';
            $charset = 'utf8mb4';
        } elseif ($type === 'sqlite' || strpos($adapter, 'SQLite') !== false) {
            $sqlFile = 'sqlite';
            $charset = '';
        } elseif ($type === 'pgsql' || strpos($adapter, 'Pgsql') !== false) {
            $sqlFile = 'pgsql';
            $charset = '';
        } else {
            throw new PluginException(_t('不支持的数据库类型：%s', $adapter));
        }

        $path = dirname(__FILE__) . '/sql/' . $sqlFile . '.sql';
        if (!is_file($path)) {
            throw new PluginException(_t('找不到建表脚本：%s', $sqlFile . '.sql'));
        }

        $scripts = file_get_contents($path);
        $scripts = str_replace('typecho_', $prefix, $scripts);
        if ($charset !== '') {
            $scripts = str_replace('%charset%', $charset, $scripts);
        }

        $lines = preg_split('/\r\n|\r|\n/', $scripts);
        $clean = array();
        foreach ($lines as $line) {
            $trim = ltrim($line);
            if ($trim === '' || strpos($trim, '--') === 0) {
                continue;
            }
            $clean[] = $line;
        }
        $scripts = implode("\n", $clean);

        $statements = explode(';', $scripts);
        try {
            foreach ($statements as $stmt) {
                $stmt = trim($stmt);
                if ($stmt !== '') {
                    $db->query($stmt, Db::WRITE);
                }
            }
        } catch (\Exception $e) {
            throw new PluginException(_t('AstraHub 数据表建立失败：%s', $e->getMessage()));
        }

        return 'AstraHub 星链插件已启用，数据表已就绪';
    }
}
