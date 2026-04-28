<?php
if (!defined('ABSPATH')) exit;

class MMCA_Collector {
    private $db;
    public function __construct(MMCA_DB $db) { $this->db = $db; }

    public function collect_status() {
        global $wpdb;
        if (!function_exists('get_plugin_updates')) {
            require_once ABSPATH . 'wp-admin/includes/update.php';
        }
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        wp_update_plugins();
        wp_update_themes();

        $site_url = home_url('/');
        $site_uuid = (string) get_option('mmca_site_uuid', '');
        if ($site_uuid === '') {
            $site_uuid = wp_generate_uuid4();
            update_option('mmca_site_uuid', $site_uuid, false);
        }

        $theme = wp_get_theme();
        $active_plugins = (array) get_option('active_plugins', []);
        $all_plugins = get_plugins();
        $plugin_updates = get_plugin_updates();
        $core_updates = get_core_updates();
        $warnings = 0;
        $criticals = 0;
        $signals = [];
        $advice = [];

        $backup_plugins = ['installed' => [], 'active' => []];
        $security_plugins = ['installed' => [], 'active' => []];

        $versions = [
            'wp' => get_bloginfo('version'),
            'php' => PHP_VERSION,
            'db' => method_exists($wpdb, 'db_version') ? (string) $wpdb->db_version() : '',
            'mysql' => isset($wpdb->db_server_info) ? (string) $wpdb->db_server_info() : '',
        ];

        if (version_compare(PHP_VERSION, '8.1', '<')) {
            $warnings += 2;
            $signals[] = 'PHPのバージョンがやや古めです。';
            $advice[] = 'PHPの更新準備を進めると、今後の安定運用につながりやすくなります。';
        }
        if (count($plugin_updates) >= 10) {
            $warnings += 2;
            $signals[] = 'プラグインの更新候補が多めです。';
            $advice[] = '更新はまとめて行わず、事前確認のうえで段階的に進めるのがおすすめです。';
        } elseif (count($plugin_updates) >= 5) {
            $warnings++;
            $signals[] = '更新候補が少し溜まってきています。';
        }
        if (!empty($core_updates) && !empty($core_updates[0]->response) && $core_updates[0]->response !== 'latest') {
            $warnings++;
            $signals[] = 'WordPress本体に更新候補があります。';
        }


        $debug = $this->collect_debug_status();
        if (!empty($debug['display_enabled'])) {
            $warnings += 2;
            $signals[] = 'デバッグ情報が画面に表示される設定の可能性があります。';
        }


        $hq_base = untrailingslashit((string) get_option('mmca_hq_base_url', ''));
        $external_http_blocked = null;
        $health_checked_at = current_time('mysql');
        if ($hq_base !== '') {
            $resp = wp_remote_get($hq_base . '/health', ['timeout' => 8, 'sslverify' => true]);
            $external_http_blocked = is_wp_error($resp);
            if (is_wp_error($resp)) {
                $signals[] = '本部との通信確認に失敗しました。閉域環境の場合は問題ありません。';
            }
        }

        $mode = (string) get_option('mmca_mode', 'local_only');
        $score = max(0, min(100, ($warnings * 10) + ($criticals * 25)));
        $payload = [
            'site_uuid' => $site_uuid,
            'site_name' => get_bloginfo('name') ?: parse_url($site_url, PHP_URL_HOST),
            'domain' => home_url('/'),
            'wp_version' => $versions['wp'],
            'php_version' => $versions['php'],
            'plan' => 'local',
            'score' => $score,
            'status_label' => $this->status_label_from_score($score),
            'warning_count' => $warnings,
            'critical_count' => $criticals,
            'theme' => [
                'name' => $theme->get('Name'),
                'version' => $theme->get('Version'),
            ],
            'plugins' => [
                'active_count' => count($active_plugins),
                'update_candidates' => count($plugin_updates),
                'backup' => $backup_plugins,
                'security' => $security_plugins,
            ],
            'versions' => $versions,
            'storage' => ['free_bytes' => null, 'total_bytes' => null, 'free_percent' => null, 'uploads_bytes' => null, 'is_tight' => false],
            'security_posture' => ['has_basic_protection' => null, 'xmlrpc_enabled' => null, 'file_edit_locked' => null, 'user_registration_open' => null, 'summary' => '詳細な保護状態は、この画面では控えめに表示しています。'],
            'debug' => $debug,
            'core_updates' => !empty($core_updates) ? wp_json_encode($core_updates) : '',
            'signals' => array_values(array_unique($signals)),
            'mode' => $mode,
            'connectivity' => [
                'external_http_blocked' => $external_http_blocked,
                'outbound_http_status' => $external_http_blocked === null ? 'unchecked' : ($external_http_blocked ? 'blocked_or_failed' : 'ok'),
                'internal_reporting_capable' => true,
                'local_monitoring_capable' => !(defined('DISABLE_WP_CRON') && DISABLE_WP_CRON),
                'local_backup_capable' => is_writable(WP_CONTENT_DIR),
                'local_storage_writable' => is_writable(WP_CONTENT_DIR),
                'local_cron_available' => !(defined('DISABLE_WP_CRON') && DISABLE_WP_CRON),
                'monitor_agent_present' => true,
                'backup_agent_present' => null,
                'checked_at' => $health_checked_at,
            ],
            'requester_name' => (string) get_option('mmca_requester_name', ''),
            'requester_email' => (string) get_option('mmca_requester_email', ''),
            'client_version' => MMCA_VERSION,
            'captured_at' => current_time('mysql'),
            'friendly_summary' => $this->friendly_summary($score, $signals, $advice),
            'recommended_next_steps' => $this->recommended_steps($score, $signals, $advice),
            'analysis_cards' => $this->build_analysis_cards($versions, $backup_plugins, $security_plugins, array(), array(), $debug, count($plugin_updates)),
        ];
        return $payload;
    }

    private function find_matching_plugins(array $active_plugins, array $all_plugins, array $keywords) {
        $installed = [];
        $active = [];
        foreach ($all_plugins as $file => $plugin) {
            $hay = strtolower($file . ' ' . ($plugin['Name'] ?? ''));
            foreach ($keywords as $keyword) {
                if (strpos($hay, strtolower($keyword)) !== false) {
                    $name = (string) ($plugin['Name'] ?? $file);
                    $installed[] = $name;
                    if (in_array($file, $active_plugins, true)) {
                        $active[] = $name;
                    }
                    break;
                }
            }
        }
        return [
            'installed' => array_values(array_unique($installed)),
            'active' => array_values(array_unique($active)),
        ];
    }

    private function collect_storage_status() {
        $path = ABSPATH;
        $free = function_exists('disk_free_space') ? @disk_free_space($path) : false;
        $total = function_exists('disk_total_space') ? @disk_total_space($path) : false;
        $free_pct = null;
        if ($free !== false && $total) {
            $free_pct = round(($free / $total) * 100, 1);
        }
        $uploads = wp_upload_dir();
        $uploads_size = $this->dir_size($uploads['basedir'] ?? '');
        return [
            'free_bytes' => $free !== false ? (int) $free : null,
            'total_bytes' => $total !== false ? (int) $total : null,
            'free_percent' => $free_pct,
            'uploads_bytes' => $uploads_size,
            'is_tight' => $free_pct !== null ? ($free_pct <= 15) : false,
        ];
    }

    private function dir_size($dir) {
        if (!$dir || !is_dir($dir)) return null;
        $size = 0;
        try {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $size += $file->getSize();
                    if ($size > 5 * 1024 * 1024 * 1024) break;
                }
            }
        } catch (Exception $e) {
            return null;
        }
        return (int) $size;
    }

    private function collect_security_posture(array $security_plugins) {
        $xmlrpc_enabled = apply_filters('xmlrpc_enabled', true);
        $disallow_file_edit = defined('DISALLOW_FILE_EDIT') && DISALLOW_FILE_EDIT;
        $register_open = (bool) get_option('users_can_register', false);
        $has_basic = !empty($security_plugins['active']) || $disallow_file_edit;
        return [
            'has_basic_protection' => $has_basic,
            'xmlrpc_enabled' => (bool) $xmlrpc_enabled,
            'file_edit_locked' => $disallow_file_edit,
            'user_registration_open' => $register_open,
            'summary' => $has_basic ? '基本的な保護設定は入っています。' : '基本的な保護設定の見直し余地があります。',
        ];
    }

    private function collect_debug_status() {
        $display = false;
        if (defined('WP_DEBUG_DISPLAY')) {
            $display = (bool) WP_DEBUG_DISPLAY;
        } else {
            $val = ini_get('display_errors');
            $display = !empty($val) && $val !== '0';
        }
        $log_path = WP_CONTENT_DIR . '/debug.log';
        $recent_parse_error = false;
        if (is_readable($log_path)) {
            $tail = $this->read_tail($log_path, 250000);
            if ($tail && preg_match('/(PHP Parse error|syntax error)/i', $tail)) {
                $recent_parse_error = true;
            }
        }
        return [
            'wp_debug' => defined('WP_DEBUG') ? (bool) WP_DEBUG : false,
            'display_enabled' => $display,
            'log_exists' => file_exists($log_path),
            'recent_parse_error' => $recent_parse_error,
        ];
    }

    private function read_tail($path, $bytes = 120000) {
        $size = @filesize($path);
        if (!$size) return '';
        $fh = @fopen($path, 'rb');
        if (!$fh) return '';
        $seek = max(0, $size - $bytes);
        fseek($fh, $seek);
        $data = fread($fh, $bytes);
        fclose($fh);
        return (string) $data;
    }

    private function build_analysis_cards(array $versions, array $backup_plugins, array $security_plugins, array $storage, array $security, array $debug, $update_count) {
        return [
            [
                'key' => 'versions',
                'title' => 'サーバー環境',
                'status' => version_compare($versions['php'], '8.1', '>=') ? '比較的安定した範囲です' : '更新準備がおすすめです',
                'detail' => 'PHP ' . $versions['php'] . ' / DB ' . ($versions['db'] ?: '未取得') . ' を確認しました。',
            ],
            [
                'key' => 'updates',
                'title' => '更新状況',
                'status' => $update_count >= 5 ? '更新候補が溜まり気味です' : '大きくは溜まっていません',
                'detail' => '更新候補は ' . (int) $update_count . ' 件です。安全確認なしで一括更新はおすすめしません。',
            ],
            [
                'key' => 'debug',
                'title' => '公開時の設定',
                'status' => !empty($debug['display_enabled']) ? '本番向けに確認がおすすめです' : '落ち着いています',
                'detail' => !empty($debug['display_enabled']) ? 'デバッグ表示は本番公開向けに見直しがおすすめです。' : '画面表示まわりは大きな懸念が見えにくい状態です。',
            ],
        ];
    }

    public function capture_snapshot() {
        $payload = $this->collect_status();
        $this->db->insert_snapshot($payload, 'local', 'status');
        update_option('mmca_last_snapshot_at', current_time('mysql'));
        update_option('mmca_last_diagnosis_score', (int) ($payload['score'] ?? 0));
        update_option('mmca_last_diagnosis_label', (string) ($payload['status_label'] ?? ''));
        update_option('mmca_last_diagnosis_summary', (string) ($payload['friendly_summary'] ?? ''));
        update_option('mmca_last_diagnosis_steps', $payload['recommended_next_steps'] ?? []);
        update_option('mmca_onboarding_completed', 'yes');
        return $payload;
    }

    public function status_label_from_score($score) {
        if ($score >= 60) return '優先的に確認したい項目があります';
        if ($score >= 30) return '確認しておくと安心な点があります';
        return '現在は安定しています';
    }

    public function friendly_summary($score, array $signals, array $advice = []) {
        if ($score >= 60) {
            return 'すぐに大きなトラブルが起きると断定はできませんが、早めに見直しておくと安心な項目があります。';
        }
        if ($score >= 30) {
            return '今すぐ慌てる必要はありませんが、時間のあるうちに見直しておくと安心です。';
        }
        if (!empty($signals)) {
            return '現在は大きな問題は見つかっていません。小さな確認を続けることで、より安心して運用しやすくなります。';
        }
        return '現在は大きな問題は見つかっていません。このまま定期的に見守っていくことで安心につながります。';
    }

    public function recommended_steps($score, array $signals, array $advice = []) {
        $steps = [];
        if ($score >= 60) {
            $steps[] = '更新候補や環境設定は、事前確認のうえで段階的に見直しましょう。';
            $steps[] = '無料のみまもりサポートを開始すると、継続的な確認につなげやすくなります。';
        } elseif ($score >= 30) {
            $steps[] = '今月中に更新候補や設定を見直しておくと安心です。';
            $steps[] = '無料のみまもりサポートを始めると、状態の変化に気づきやすくなります。';
        } else {
            $steps[] = '現在の状態を維持しながら、定期的に確認を続けましょう。';
            $steps[] = '無料のみまもりサポートを始めると、今後の変化にも気づきやすくなります。';
        }
        foreach (array_slice($advice, 0, 3) as $item) {
            $steps[] = $item;
        }
        if (!empty($signals)) {
            $steps[] = '気になる点: ' . implode(' / ', array_slice($signals, 0, 2));
        }
        return array_values(array_unique($steps));
    }
}
