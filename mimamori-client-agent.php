<?php
/*
Plugin Name: みまもり無料診断
Description: サイトの初回診断、やさしい状態表示、無料のみまもりサポート開始、同意後の接続申請、診断データ出力を行うクライアント側エージェントです。
Version: 0.4.0
Author: Healing Solutions
Requires at least: 6.0
Requires PHP: 7.4
Text Domain: mimamori-client-agent
License: GPLv2 or later
*/

if (!defined('ABSPATH')) exit;

define('MMCA_VERSION', '0.4.0');
define('MMCA_FILE', __FILE__);
define('MMCA_DIR', plugin_dir_path(__FILE__));
define('MMCA_URL', plugin_dir_url(__FILE__));

require_once MMCA_DIR . 'includes/class-mmca-plugin.php';
require_once MMCA_DIR . 'includes/class-mmca-db.php';
require_once MMCA_DIR . 'includes/class-mmca-collector.php';
require_once MMCA_DIR . 'includes/class-mmca-api-client.php';
require_once MMCA_DIR . 'includes/class-mmca-admin.php';

function mmca() {
    static $instance = null;
    if ($instance === null) {
        $instance = new MMCA_Plugin();
    }
    return $instance;
}

register_activation_hook(__FILE__, ['MMCA_Plugin', 'activate']);
add_action('plugins_loaded', function(){ mmca(); });
