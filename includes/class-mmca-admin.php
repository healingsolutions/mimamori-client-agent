<?php
if (!defined('ABSPATH')) exit;

class MMCA_Admin {
    private $collector;
    private $api;
    private $db;

    public function __construct(MMCA_Collector $collector, MMCA_API_Client $api, MMCA_DB $db) {
        $this->collector = $collector;
        $this->api = $api;
        $this->db = $db;
        add_action('admin_menu', [$this, 'menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('wp_dashboard_setup', [$this, 'register_dashboard_widget']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    public function menu() {
        add_menu_page('みまもりエージェント', 'みまもりエージェント', 'manage_options', 'mmca_dashboard', [$this, 'render'], 'dashicons-shield', 58);
        add_submenu_page('mmca_dashboard', 'みまもりの詳細設定', '詳細設定', 'manage_options', 'mmca_settings', [$this, 'render_settings']);
    }

    public function register_settings() {
        register_setting('mmca_settings', 'mmca_hq_base_url', ['sanitize_callback' => 'esc_url_raw']);
        register_setting('mmca_settings', 'mmca_hq_token', ['sanitize_callback' => 'sanitize_text_field']);
        register_setting('mmca_settings', 'mmca_requester_name', ['sanitize_callback' => 'sanitize_text_field']);
        register_setting('mmca_settings', 'mmca_requester_email', ['sanitize_callback' => 'sanitize_email']);
        register_setting('mmca_settings', 'mmca_mode', ['sanitize_callback' => [$this, 'sanitize_mode']]);
        register_setting('mmca_settings', 'mmca_show_dashboard_widget', ['sanitize_callback' => [$this, 'sanitize_yes_no']]);
    }

    public function sanitize_mode($value) {
        $allowed = ['local_only', 'hq_required'];
        return in_array($value, $allowed, true) ? $value : 'local_only';
    }

    public function sanitize_yes_no($value) {
        return $value === 'no' ? 'no' : 'yes';
    }

    public function enqueue_assets($hook) {
        if (strpos((string) $hook, 'mmca') === false && $hook !== 'index.php') return;

        $css = <<<CSS
.mmca-shell{max-width:1240px}
.mmca-surface{background:linear-gradient(180deg,#f6fbff,#ffffff);border:1px solid #dbeafe;border-radius:24px;padding:28px;box-shadow:0 16px 40px rgba(15,23,42,.06)}
.mmca-card{background:#fff;border:1px solid #e5eef7;border-radius:22px;padding:24px;margin:18px 0;box-shadow:0 10px 30px rgba(15,23,42,.04)}
.mmca-hero{padding:34px;background:radial-gradient(circle at top left,#eef6ff,#ffffff 60%);border:1px solid #d7e8ff;border-radius:28px;box-shadow:0 18px 40px rgba(59,130,246,.08)}
.mmca-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:16px}
.mmca-grid-2{display:grid;grid-template-columns:1.1fr .9fr;gap:18px}
.mmca-metric,.mmca-panel{background:#fff;border:1px solid #e7edf5;border-radius:18px;padding:18px 18px 16px;box-shadow:0 8px 20px rgba(15,23,42,.03)}
.mmca-kicker{font-size:12px;font-weight:700;letter-spacing:.08em;color:#2563eb;text-transform:uppercase}
.mmca-title{font-size:30px;line-height:1.25;margin:8px 0 12px}
.mmca-muted{color:#5b6472}
.mmca-badge{display:inline-flex;align-items:center;gap:6px;padding:8px 12px;border-radius:999px;background:#ecfdf3;color:#166534;font-weight:700;font-size:12px}
.mmca-badge.warn{background:#fff7ed;color:#9a3412}
.mmca-badge.alert{background:#fef2f2;color:#b91c1c}
.mmca-actions{display:flex;gap:10px;flex-wrap:wrap}
.mmca-actions .button{border-radius:999px;padding:0 18px;min-height:40px}
.mmca-progress-wrap{max-width:560px}
.mmca-progress{height:14px;border-radius:999px;background:#e9eef5;overflow:hidden}
.mmca-progress-bar{height:100%;width:0;background:linear-gradient(90deg,#4f8ef7,#5ad2b4);transition:width .35s}
.mmca-small{font-size:12px;color:#667085}
.mmca-step-list{margin:0;padding-left:18px}
.mmca-step-list li{margin:0 0 9px}
.mmca-note{padding:14px 16px;border-left:4px solid #60a5fa;background:#f8fbff;border-radius:0 14px 14px 0}
.mmca-card-title{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:14px}
.mmca-analysis-card h3{margin:0 0 8px;font-size:16px}
.mmca-analysis-card p{margin:0;color:#5b6472}
.mmca-kv th{width:220px;text-align:left;padding:12px;background:#f8fafc}
.mmca-kv td{padding:12px}
.mmca-widget{padding:18px;border-radius:18px;background:linear-gradient(180deg,#eff7ff,#ffffff);border:1px solid #dcecff}
.mmca-stack{display:grid;gap:16px}
.mmca-empty{padding:22px;border:1px dashed #cbd5e1;border-radius:18px;background:#fff}
.mmca-subgrid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:14px}
.mmca-section-heading{font-size:21px;margin:0 0 8px}
.mmca-state-banner{display:grid;gap:16px;grid-template-columns:minmax(0,1fr) auto;align-items:start}
.mmca-secondary-list{display:flex;flex-wrap:wrap;gap:10px;margin-top:14px}
.mmca-secondary-list form{margin:0}
.mmca-detail-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:14px}
.mmca-detail-box{background:#fff;border:1px solid #e5eef7;border-radius:16px;padding:16px}
.mmca-details summary{cursor:pointer;font-weight:700}
.mmca-details[open] summary{margin-bottom:14px}
.mmca-inline-code{font-family:Consolas,monospace;font-size:12px;background:#f8fafc;padding:2px 6px;border-radius:999px}
@media (max-width:960px){.mmca-grid-2{grid-template-columns:1fr}.mmca-state-banner{grid-template-columns:1fr}}
CSS;

        wp_register_style('mmca-inline', false, [], MMCA_VERSION);
        wp_enqueue_style('mmca-inline');
        wp_add_inline_style('mmca-inline', $css);

        $js = <<<JS
document.addEventListener('DOMContentLoaded',function(){
  document.querySelectorAll('[data-mmca-progress-form]').forEach(function(form){
    var btn=form.querySelector('[data-mmca-start]');
    if(!btn)return;
    btn.addEventListener('click',function(e){
      e.preventDefault();
      var wrap=form.querySelector('.mmca-progress-wrap');
      if(wrap)wrap.style.display='block';
      var bar=form.querySelector('.mmca-progress-bar');
      var text=form.querySelector('.mmca-progress-text');
      var steps=['WordPress本体を確認しています','プラグイン構成を確認しています','基本設定と更新状況を確認しています','結果をまとめています'];
      var i=0,p=10;
      function tick(){
        if(bar)bar.style.width=p+'%';
        if(text)text.textContent=steps[Math.min(i,steps.length-1)];
        p+=20;i++;
        if(p<100){setTimeout(tick,500);}
        else{
          if(bar)bar.style.width='100%';
          if(text)text.textContent='診断を完了しています';
          setTimeout(function(){form.submit();},350);
        }
      }
      tick();
    });
  });
});
JS;

        wp_register_script('mmca-inline-js', '', [], MMCA_VERSION, true);
        wp_enqueue_script('mmca-inline-js');
        wp_add_inline_script('mmca-inline-js', $js);
    }

    private function redirect_notice($type, $message, array $args = []) {
        $query = array_merge([
            'page' => 'mmca_dashboard',
            'mmca_notice' => rawurlencode($message),
            'mmca_notice_type' => $type,
        ], $args);

        wp_safe_redirect(add_query_arg($query, admin_url('admin.php')));
        exit;
    }

    private function error_state_key() {
        return 'mmca_ui_error_' . get_current_user_id();
    }

    private function remember_error_state($action, $message) {
        set_transient($this->error_state_key(), [
            'action' => sanitize_key((string) $action),
            'message' => sanitize_text_field((string) $message),
            'recorded_at' => current_time('mysql'),
        ], 15 * MINUTE_IN_SECONDS);
    }

    private function clear_error_state() {
        delete_transient($this->error_state_key());
    }

    private function get_error_state() {
        $state = get_transient($this->error_state_key());
        return is_array($state) ? $state : null;
    }

    private function verify_post() {
        if (!current_user_can('manage_options')) {
            wp_die('権限がありません。');
        }
        check_admin_referer('mmca_action');
    }

    public function capture_snapshot() {
        $this->verify_post();
        $this->collector->capture_snapshot();
        $this->clear_error_state();
        $this->redirect_notice('success', 'サイトの状態確認が完了しました。');
    }

    public function send_connection_request() {
        $this->verify_post();
        $result = $this->api->request_connection();
        if (is_wp_error($result)) {
            $this->remember_error_state('mmca_send_connection_request', $result->get_error_message());
            $this->redirect_notice('error', $result->get_error_message());
        }
        update_option('mmca_last_connection_status', (string) ($result['status'] ?? 'pending'));
        update_option('mmca_support_enabled', 'yes');
        $this->clear_error_state();
        $this->redirect_notice('success', '無料のみまもりサポートの開始申請を送信しました。');
    }

    public function check_connection_status() {
        $this->verify_post();
        $result = $this->api->check_connection_status();
        if (is_wp_error($result)) {
            $this->remember_error_state('mmca_check_connection_status', $result->get_error_message());
            $this->redirect_notice('error', $result->get_error_message());
        }

        $status = (string) ($result['status'] ?? 'unknown');
        $labels = [
            'pending' => 'みまもりポータルで内容を確認しています。',
            'approved' => 'みまもりポータルとの連携が有効になりました。',
            'rejected' => '今回は連携開始を見合わせています。必要な場合は診断データをお渡しください。',
        ];
        $this->clear_error_state();
        $this->redirect_notice('success', $labels[$status] ?? ('現在の状態: ' . $status));
    }

    public function sync_now() {
        $this->verify_post();
        $result = $this->api->sync_now();
        if (is_wp_error($result)) {
            $this->remember_error_state('mmca_sync_now', $result->get_error_message());
            $this->redirect_notice('error', $result->get_error_message());
        }
        $this->clear_error_state();
        $this->redirect_notice('success', '本部へ最新の状態を送信しました。');
    }

    public function download_snapshot() {
        $this->verify_post();
        $payload = $this->db->latest_snapshot_payload();
        if (!$payload) {
            $payload = $this->collector->capture_snapshot();
        }

        nocache_headers();
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="mimamori-snapshot-' . gmdate('Ymd-His') . '.json"');
        echo wp_json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public function register_dashboard_widget() {
        if (get_option('mmca_show_dashboard_widget', 'yes') !== 'yes') return;
        wp_add_dashboard_widget('mmca_dashboard_widget', 'みまもりサポート', [$this, 'render_dashboard_widget']);
    }

    public function render_dashboard_widget() {
        $latest = $this->db->latest_snapshot_payload();
        echo '<div class="mmca-widget">';
        if (!$latest) {
            echo '<p><strong>まだ診断をしていません。</strong></p>';
            echo '<p class="mmca-small">まずはサイトの状態をやさしく確認しましょう。</p>';
            echo '<p><a class="button button-primary" href="' . esc_url(admin_url('admin.php?page=mmca_dashboard')) . '">最初に診断を始める</a></p></div>';
            return;
        }

        $score = (int) ($latest['score'] ?? 0);
        $badge = $this->status_badge((string) ($latest['status_label'] ?? '状態を確認しました'), $score);
        echo '<div class="mmca-card-title"><div>' . $badge . '</div><a class="button" href="' . esc_url(admin_url('admin.php?page=mmca_dashboard')) . '">詳しく見る</a></div>';
        echo '<p style="font-size:14px;line-height:1.7;margin:0 0 8px;">' . esc_html($latest['friendly_summary'] ?? '') . '</p>';
        echo '<p class="mmca-small">最終確認: ' . esc_html($latest['captured_at'] ?? '') . '</p>';
        if (!empty($latest['analysis_cards'][0])) {
            echo '<p class="mmca-small" style="margin-top:10px;">' . esc_html($latest['analysis_cards'][0]['title'] . '：' . $latest['analysis_cards'][0]['status']) . '</p>';
        }
        echo '</div>';
    }

    private function status_badge($label, $score) {
        $class = 'mmca-badge';
        if ($score >= 60) {
            $class .= ' alert';
        } elseif ($score >= 30) {
            $class .= ' warn';
        }
        return '<span class="' . esc_attr($class) . '">' . esc_html($label) . '</span>';
    }

    private function get_notice() {
        return [
            'message' => isset($_GET['mmca_notice']) ? sanitize_text_field(wp_unslash($_GET['mmca_notice'])) : '',
            'type' => isset($_GET['mmca_notice_type']) ? sanitize_text_field(wp_unslash($_GET['mmca_notice_type'])) : 'success',
        ];
    }

    private function build_view_context() {
        $latest = $this->db->latest_snapshot_payload();
        $status = (string) get_option('mmca_last_connection_status', 'not_requested');
        $token = (string) get_option('mmca_hq_token', '');
        $mode = (string) get_option('mmca_mode', 'local_only');
        $hq_base_url = (string) get_option('mmca_hq_base_url', '');
        $notice = $this->get_notice();

        return [
            'latest' => $latest,
            'rows' => $this->db->recent_rows(10),
            'status' => $status,
            'mode' => $mode,
            'hq_base_url' => $hq_base_url,
            'has_hq_base_url' => $hq_base_url !== '',
            'token' => $token,
            'has_token' => $token !== '',
            'support_enabled' => get_option('mmca_support_enabled', 'no') === 'yes',
            'onboarding_completed' => get_option('mmca_onboarding_completed', 'no') === 'yes',
            'requester_name' => (string) get_option('mmca_requester_name', ''),
            'requester_email' => (string) get_option('mmca_requester_email', ''),
            'site_uuid' => (string) get_option('mmca_site_uuid', ''),
            'last_snapshot_at' => (string) get_option('mmca_last_snapshot_at', ''),
            'last_connection_requested_at' => (string) get_option('mmca_last_connection_requested_at', ''),
            'last_connection_checked_at' => (string) get_option('mmca_last_connection_checked_at', ''),
            'error_state' => $this->get_error_state(),
            'notice' => $notice,
        ];
    }

    private function resolve_view_state(array $context) {
        if (empty($context['latest'])) {
            return 'welcome';
        }

        if (!empty($context['error_state'])) {
            return 'error_retry';
        }

        if ($context['status'] === 'approved' && $context['has_token']) {
            return 'connected';
        }

        if ($context['support_enabled'] && $context['status'] === 'pending') {
            return 'waiting';
        }

        if ($context['status'] === 'rejected') {
            return 'not_approved';
        }

        if ($context['mode'] === 'local_only' || !$context['has_hq_base_url']) {
            return 'local_only';
        }

        return 'result';
    }

    private function render_state_banner($kicker, $title, $body, $badge_html = '') {
        echo '<div class="mmca-hero"><div class="mmca-state-banner"><div>';
        echo '<div class="mmca-kicker">' . esc_html($kicker) . '</div>';
        echo '<h2 class="mmca-section-heading" style="font-size:28px;">' . esc_html($title) . '</h2>';
        echo '<p class="mmca-muted" style="max-width:760px;line-height:1.85;margin:0;">' . esc_html($body) . '</p>';
        echo '</div>';
        if ($badge_html !== '') {
            echo '<div>' . $badge_html . '</div>';
        }
        echo '</div></div>';
    }

    private function open_action_form($action, $label, $primary = false, $attrs = '') {
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" ' . $attrs . '>';
        wp_nonce_field('mmca_action');
        echo '<input type="hidden" name="action" value="' . esc_attr($action) . '" />';
        echo '<button type="submit" class="button' . ($primary ? ' button-primary' : '') . '">' . esc_html($label) . '</button>';
        echo '</form>';
    }

    private function render_secondary_actions(array $actions) {
        if (empty($actions)) {
            return;
        }

        echo '<div class="mmca-card">';
        echo '<div class="mmca-card-title"><h3 style="margin:0;">ほかにできること</h3></div>';
        echo '<div class="mmca-secondary-list">';
        foreach ($actions as $action) {
            $this->open_action_form($action['action'], $action['label'], false, !empty($action['attrs']) ? $action['attrs'] : '');
        }
        echo '</div></div>';
    }

    private function render_snapshot_overview(array $latest) {
        $score = (int) ($latest['score'] ?? 0);
        $label = (string) ($latest['status_label'] ?? '状態を確認しました');
        $steps = !empty($latest['recommended_next_steps']) ? (array) $latest['recommended_next_steps'] : [];

        echo '<div class="mmca-grid" style="margin-top:18px;">';
        echo '<div class="mmca-metric"><div class="mmca-kicker">総合状態</div><p style="font-size:20px;font-weight:700;margin:10px 0 0;">' . esc_html($label) . '</p><p class="mmca-small">今の状態をやさしくまとめています。</p></div>';
        echo '<div class="mmca-metric"><div class="mmca-kicker">更新候補</div><p style="font-size:20px;font-weight:700;margin:10px 0 0;">' . esc_html((string) ($latest['plugins']['update_candidates'] ?? 0)) . '件</p><p class="mmca-small">自動更新は行わず、確認ベースでご案内します。</p></div>';
        echo '<div class="mmca-metric"><div class="mmca-kicker">最終確認</div><p style="font-size:20px;font-weight:700;margin:10px 0 0;">' . esc_html($latest['captured_at'] ?? '未確認') . '</p><p class="mmca-small">必要なときにいつでも再確認できます。</p></div>';
        echo '<div class="mmca-metric"><div class="mmca-kicker">見守りメモ</div><p style="font-size:20px;font-weight:700;margin:10px 0 0;">' . ($score >= 60 ? '早めの確認がおすすめです' : ($score >= 30 ? '時間のあるうちに確認すると安心です' : '現在は落ち着いています')) . '</p><p class="mmca-small">いま大切なことだけを短くお伝えします。</p></div>';
        echo '</div>';

        echo '<div class="mmca-grid-2" style="margin-top:18px;">';
        echo '<div class="mmca-card">';
        echo '<div class="mmca-card-title"><h2 style="margin:0;">今回の診断まとめ</h2><span class="mmca-small">' . $this->status_badge($label, $score) . '</span></div>';
        echo '<p style="line-height:1.9;margin-top:0;">' . esc_html($latest['friendly_summary'] ?? '') . '</p>';
        echo '<div class="mmca-note">この診断だけで更新・削除・設定変更が勝手に行われることはありません。本部へ共有する場合も、画面上の申請・送信操作を押したときだけです。</div>';
        if (!empty($latest['analysis_cards'])) {
            echo '<div class="mmca-subgrid" style="margin-top:16px;">';
            foreach ((array) $latest['analysis_cards'] as $card) {
                echo '<div class="mmca-panel mmca-analysis-card">';
                echo '<h3>' . esc_html($card['title'] ?? '') . '</h3>';
                echo '<p style="font-weight:700;color:#0f172a;margin-bottom:8px;">' . esc_html($card['status'] ?? '') . '</p>';
                echo '<p>' . esc_html($card['detail'] ?? '') . '</p>';
                echo '</div>';
            }
            echo '</div>';
        }
        echo '</div>';

        echo '<div class="mmca-stack">';
        echo '<div class="mmca-card"><div class="mmca-card-title"><h2 style="margin:0;">確認しておきたい点</h2></div>';
        if (!empty($latest['signals'])) {
            echo '<ul class="mmca-step-list">';
            foreach ((array) $latest['signals'] as $signal) {
                echo '<li>' . esc_html($signal) . '</li>';
            }
            echo '</ul>';
        } else {
            echo '<div class="mmca-empty">現在のところ、大きな確認事項は見つかっていません。</div>';
        }
        echo '</div>';

        echo '<div class="mmca-card"><div class="mmca-card-title"><h2 style="margin:0;">今後のおすすめ</h2></div>';
        if ($steps) {
            echo '<ul class="mmca-step-list">';
            foreach ($steps as $step) {
                echo '<li>' . esc_html($step) . '</li>';
            }
            echo '</ul>';
        } else {
            echo '<div class="mmca-empty">必要なときに再診断すると、変化が分かりやすくなります。</div>';
        }
        echo '</div></div></div>';
    }

    private function render_welcome_state(array $context) {
        $this->render_state_banner(
            'はじめに',
            '最初に診断を始めましょう',
            'このサイトの更新状況、基本設定、公開時に気をつけたい点などを順番に確認して、今の状態をやさしくお伝えします。'
        );

        echo '<div class="mmca-card">';
        echo '<div class="mmca-card-title"><h2 style="margin:0;">まず最初の一歩だけ進めます</h2></div>';
        echo '<p>難しい専門用語を並べずに、現在の状態を確認します。診断が終わったあとに、必要な場合だけ無料のみまもりサポートへ進めます。</p>';
        echo '<div class="mmca-subgrid" style="margin-top:18px;">';
        echo '<div class="mmca-panel"><h3>診断すること</h3><p>更新候補、基本設定、公開時に気をつけたい点を確認し、今の状態を短くまとめます。</p></div>';
        echo '<div class="mmca-panel"><h3>勝手にしないこと</h3><p>診断だけで更新、削除、設定変更、外部送信を自動で行うことはありません。</p></div>';
        echo '<div class="mmca-panel"><h3>次に進む条件</h3><p>無料サポートの開始申請は診断後に選べます。必要なければ、このサイト内だけで使い続けられます。</p></div>';
        echo '</div>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" data-mmca-progress-form>';
        wp_nonce_field('mmca_action');
        echo '<input type="hidden" name="action" value="mmca_capture_snapshot" />';
        echo '<div class="mmca-actions" style="margin-top:18px;"><button type="submit" class="button button-primary" data-mmca-start>最初に診断を始める</button></div>';
        echo '<div class="mmca-progress-wrap" style="display:none;margin-top:18px;"><div class="mmca-progress"><div class="mmca-progress-bar"></div></div><p class="mmca-progress-text mmca-small" style="margin-top:8px;">診断の準備をしています</p></div>';
        echo '</form>';
        echo '</div>';
    }

    private function render_result_state(array $context) {
        $this->render_state_banner(
            '診断完了',
            'サイトの状態を確認しました',
            '今の状態を分かりやすく整理しました。必要であれば、このまま無料のみまもりサポートの開始申請に進めます。',
            $this->status_badge((string) ($context['latest']['status_label'] ?? '状態を確認しました'), (int) ($context['latest']['score'] ?? 0))
        );
        $this->render_snapshot_overview($context['latest']);

        echo '<div class="mmca-card">';
        echo '<div class="mmca-card-title"><div><div class="mmca-kicker">次にやること</div><h2 style="margin:6px 0 0;">必要な場合は無料のみまもりサポートを始められます</h2></div></div>';
        echo '<p>診断結果をもとに、みまもりポータル側で確認を受けられます。申請しても、サイトの更新や修正が自動で行われることはありません。</p>';
        echo '<ul class="mmca-step-list">';
        echo '<li>診断内容を送って、連携開始の確認を依頼します。</li>';
        echo '<li>承認されるまでは、サイト側で自動変更は行われません。</li>';
        echo '<li>閉域環境や手動共有がよい場合は、診断データのダウンロードを使えます。</li>';
        echo '</ul>';
        echo '<div class="mmca-actions" style="margin-top:16px;">';
        $this->open_action_form('mmca_send_connection_request', '無料のみまもりを開始する', true);
        echo '</div></div>';

        $this->render_secondary_actions([
            ['action' => 'mmca_capture_snapshot', 'label' => 'もう一度診断する', 'attrs' => 'data-mmca-progress-form'],
            ['action' => 'mmca_download_snapshot', 'label' => '診断データをダウンロードする'],
            ['action' => 'mmca_check_connection_status', 'label' => '状態を確認する'],
        ]);
    }

    private function render_waiting_state(array $context) {
        $this->render_state_banner(
            '確認待ち',
            'みまもりポータルで内容を確認しています',
            '開始申請は送信済みです。いまは確認が終わるのを待つ段階なので、必要なときだけ状態を確認してください。'
        );
        $this->render_snapshot_overview($context['latest']);

        echo '<div class="mmca-card">';
        echo '<div class="mmca-card-title"><h2 style="margin:0;">現在のご案内</h2></div>';
        echo '<p>まだ自動で何かが変わる段階ではありません。サイトはそのまま使い続けられます。</p>';
        echo '<div class="mmca-note">確認が終わると、この画面からそのまま連携状況を受け取れます。</div>';
        echo '<div class="mmca-actions" style="margin-top:16px;">';
        $this->open_action_form('mmca_check_connection_status', '状態を確認する', true);
        echo '</div></div>';

        $this->render_secondary_actions([
            ['action' => 'mmca_download_snapshot', 'label' => '診断データをダウンロードする'],
            ['action' => 'mmca_capture_snapshot', 'label' => 'もう一度診断する', 'attrs' => 'data-mmca-progress-form'],
        ]);
    }

    private function render_connected_state(array $context) {
        $this->render_state_banner(
            '連携完了',
            'みまもりポータルとの連携が有効です',
            'このサイトの最新状態を必要なタイミングで送信できます。ここでも自動更新や自動修正は行いません。'
        );
        $this->render_snapshot_overview($context['latest']);

        echo '<div class="mmca-card">';
        echo '<div class="mmca-card-title"><h2 style="margin:0;">次にやること</h2></div>';
        echo '<p>連携後は、最新の状態を送信して見守り状況を揃えやすくできます。送信しても、サイト内容が勝手に変更されることはありません。</p>';
        echo '<div class="mmca-actions" style="margin-top:16px;">';
        $this->open_action_form('mmca_sync_now', '本部へ最新の状態を送信する', true);
        echo '</div></div>';

        $this->render_secondary_actions([
            ['action' => 'mmca_check_connection_status', 'label' => '状態を確認する'],
            ['action' => 'mmca_download_snapshot', 'label' => '診断データをダウンロードする'],
            ['action' => 'mmca_capture_snapshot', 'label' => 'もう一度診断する', 'attrs' => 'data-mmca-progress-form'],
        ]);
    }

    private function render_local_only_state(array $context) {
        $this->render_state_banner(
            'ローカル利用',
            'このサイト内だけで診断を続けられます',
            '外部との自動連携を使わなくても、診断結果をファイルとして保存し、必要な相手へ共有できます。'
        );
        $this->render_snapshot_overview($context['latest']);

        echo '<div class="mmca-card">';
        echo '<div class="mmca-card-title"><h2 style="margin:0;">今のおすすめ</h2></div>';
        echo '<p>閉域環境や連携前の段階では、診断結果を手元に保存してから共有する使い方が安心です。</p>';
        echo '<div class="mmca-note">みまもりポータル連携を使いたい場合は、詳細設定で運用モードやご案内先 URL を確認してから進められます。外部送信はこの画面の操作なしには始まりません。</div>';
        echo '<div class="mmca-actions" style="margin-top:16px;">';
        $this->open_action_form('mmca_download_snapshot', '診断データをダウンロードする', true);
        echo '</div></div>';

        $this->render_secondary_actions([
            ['action' => 'mmca_send_connection_request', 'label' => '無料のみまもりを開始する'],
            ['action' => 'mmca_capture_snapshot', 'label' => 'もう一度診断する', 'attrs' => 'data-mmca-progress-form'],
        ]);
    }

    private function render_not_approved_state(array $context) {
        $this->render_state_banner(
            '今回は保留',
            '連携開始はまだ行っていません',
            '今回はみまもりポータル連携を始めていない状態です。診断結果はこのサイト内に残っているので、必要に応じて診断データとしてお渡しできます。'
        );
        $this->render_snapshot_overview($context['latest']);

        echo '<div class="mmca-card">';
        echo '<div class="mmca-card-title"><h2 style="margin:0;">今できること</h2></div>';
        echo '<p>診断データを保存して共有しながら、必要な条件が揃ってから改めて連携を検討できます。サイト側で自動変更は行われません。</p>';
        echo '<div class="mmca-actions" style="margin-top:16px;">';
        $this->open_action_form('mmca_download_snapshot', '診断データをダウンロードする', true);
        echo '</div></div>';

        $this->render_secondary_actions([
            ['action' => 'mmca_check_connection_status', 'label' => '状態を確認する'],
            ['action' => 'mmca_send_connection_request', 'label' => '無料のみまもりを再申請する'],
            ['action' => 'mmca_capture_snapshot', 'label' => 'もう一度診断する', 'attrs' => 'data-mmca-progress-form'],
        ]);
    }

    private function render_error_retry_state(array $context) {
        $primary_action = 'mmca_send_connection_request';
        $primary_label = 'もう一度お試しする';
        $error_state = is_array($context['error_state'] ?? null) ? $context['error_state'] : [];
        $error_action = (string) ($error_state['action'] ?? '');

        if ($error_action === 'mmca_sync_now' || ($context['status'] === 'approved' && $context['has_token'])) {
            $primary_action = 'mmca_sync_now';
            $primary_label = 'もう一度送信する';
        } elseif ($error_action === 'mmca_check_connection_status' || ($context['support_enabled'] && $context['status'] === 'pending')) {
            $primary_action = 'mmca_check_connection_status';
            $primary_label = 'もう一度状態を確認する';
        } elseif ($error_action === 'mmca_capture_snapshot' || !$context['latest']) {
            $primary_action = 'mmca_capture_snapshot';
            $primary_label = 'もう一度診断する';
        }

        $this->render_state_banner(
            '通信の再確認',
            '確認が途中で止まりました',
            'みまもりポータルとのやり取りが一時的に確認しづらい状態です。サイト自体に自動変更は入っていないので、そのまま使い続けられます。'
        );

        if (!empty($context['latest'])) {
            $this->render_snapshot_overview($context['latest']);
        }

        echo '<div class="mmca-card">';
        echo '<div class="mmca-card-title"><h2 style="margin:0;">まずは一度だけやり直します</h2></div>';
        echo '<p>時間をおいて再試行するだけで通る場合があります。もし急ぎで共有したい場合は、診断データをダウンロードしてお渡しください。</p>';
        if (!empty($error_state['message'])) {
            echo '<div class="mmca-note" style="margin-top:14px;">直前の確認メモ: ' . esc_html((string) $error_state['message']) . '</div>';
        }
        echo '<div class="mmca-actions" style="margin-top:16px;">';
        $this->open_action_form($primary_action, $primary_label, true, $primary_action === 'mmca_capture_snapshot' ? 'data-mmca-progress-form' : '');
        echo '</div></div>';

        $secondary = [];
        if (!empty($context['latest'])) {
            $secondary[] = ['action' => 'mmca_download_snapshot', 'label' => '診断データをダウンロードする'];
        }
        if ($primary_action !== 'mmca_capture_snapshot') {
            $secondary[] = ['action' => 'mmca_capture_snapshot', 'label' => 'もう一度診断する', 'attrs' => 'data-mmca-progress-form'];
        }
        $this->render_secondary_actions($secondary);
    }

    private function render_history_section(array $rows) {
        echo '<div class="mmca-card">';
        echo '<div class="mmca-card-title"><h2 style="margin:0;">最近の診断履歴</h2></div>';
        echo '<table class="widefat striped"><thead><tr><th>ID</th><th>種別</th><th>保存状態</th><th>作成日時</th><th>本部送信日時</th></tr></thead><tbody>';
        if ($rows) {
            foreach ($rows as $row) {
                echo '<tr>';
                echo '<td>' . (int) $row->id . '</td>';
                echo '<td>' . esc_html($row->snapshot_type) . '</td>';
                echo '<td>' . esc_html($row->sync_status) . '</td>';
                echo '<td>' . esc_html($row->created_at) . '</td>';
                echo '<td>' . esc_html($row->synced_at ?: '') . '</td>';
                echo '</tr>';
            }
        } else {
            echo '<tr><td colspan="5">まだ診断履歴がありません。</td></tr>';
        }
        echo '</tbody></table></div>';
    }

    private function render_technical_details_section(array $context) {
        $latest = $context['latest'];
        if (!$latest) {
            return;
        }

        echo '<div class="mmca-card">';
        echo '<details class="mmca-details">';
        echo '<summary>詳細な診断内容と連携情報を見る</summary>';
        echo '<div class="mmca-detail-grid">';
        echo '<div class="mmca-detail-box"><h3 style="margin-top:0;">サイト情報</h3><ul class="mmca-step-list">';
        echo '<li>サイト名: ' . esc_html($latest['site_name'] ?? '') . '</li>';
        echo '<li>ドメイン: ' . esc_html($latest['domain'] ?? '') . '</li>';
        echo '<li>最終確認: ' . esc_html($latest['captured_at'] ?? '') . '</li>';
        echo '<li>site_uuid: <span class="mmca-inline-code">' . esc_html($context['site_uuid']) . '</span></li>';
        echo '</ul></div>';

        echo '<div class="mmca-detail-box"><h3 style="margin-top:0;">バージョン</h3><ul class="mmca-step-list">';
        echo '<li>WordPress: ' . esc_html($latest['wp_version'] ?? '') . '</li>';
        echo '<li>PHP: ' . esc_html($latest['versions']['php'] ?? '') . '</li>';
        echo '<li>DB: ' . esc_html($latest['versions']['db'] ?? '') . '</li>';
        echo '<li>更新候補: ' . esc_html((string) ($latest['plugins']['update_candidates'] ?? 0)) . '件</li>';
        echo '</ul></div>';

        echo '<div class="mmca-detail-box"><h3 style="margin-top:0;">連携設定</h3><ul class="mmca-step-list">';
        echo '<li>運用モード: ' . esc_html($context['mode'] === 'hq_required' ? 'みまもりポータル連携' : 'ローカル限定') . '</li>';
        echo '<li>現在の状態: ' . esc_html($this->connection_status_label($context['status'], $context['has_token'])) . '</li>';
        echo '<li>ご案内先 URL: ' . esc_html($context['hq_base_url'] ?: '未設定') . '</li>';
        echo '<li>確認キー: ' . esc_html($context['has_token'] ? '受け取り済み' : '未受領') . '</li>';
        echo '</ul></div>';

        echo '<div class="mmca-detail-box"><h3 style="margin-top:0;">やり取り履歴</h3><ul class="mmca-step-list">';
        echo '<li>申請送信: ' . esc_html($context['last_connection_requested_at'] ?: 'まだ送信していません') . '</li>';
        echo '<li>状態確認: ' . esc_html($context['last_connection_checked_at'] ?: 'まだ確認していません') . '</li>';
        echo '<li>担当者名: ' . esc_html($context['requester_name'] ?: '未設定') . '</li>';
        echo '<li>担当者メール: ' . esc_html($context['requester_email'] ?: '未設定') . '</li>';
        echo '</ul></div>';
        echo '</div>';

        echo '<table class="widefat striped mmca-kv" style="margin-top:16px;"><tbody>';
        echo '<tr><th>診断ラベル</th><td>' . esc_html($latest['status_label'] ?? '') . '</td></tr>';
        echo '<tr><th>要約</th><td>' . esc_html($latest['friendly_summary'] ?? '') . '</td></tr>';
        echo '<tr><th>デバッグ表示</th><td>' . (!empty($latest['debug']['display_enabled']) ? '本番公開向けに確認がおすすめです' : '画面表示は抑えられていそうです') . '</td></tr>';
        echo '<tr><th>注意点</th><td>' . esc_html(!empty($latest['signals']) ? implode(' / ', (array) $latest['signals']) : '現在のところ大きな注意点は見つかっていません。') . '</td></tr>';
        echo '</tbody></table>';
        echo '</details></div>';
    }

    private function connection_status_label($status, $has_token) {
        if ($status === 'approved' && $has_token) {
            return '連携完了';
        }
        if ($status === 'pending') {
            return '確認待ち';
        }
        if ($status === 'rejected') {
            return '今回は保留';
        }
        if ($status === 'not_requested') {
            return '未申請';
        }
        return 'ローカル利用中';
    }

    public function render() {
        if (!current_user_can('manage_options')) return;

        $context = $this->build_view_context();
        $state = $this->resolve_view_state($context);

        echo '<div class="wrap mmca-shell"><div class="mmca-surface">';
        echo '<div class="mmca-card-title"><div><div class="mmca-kicker">Mimamori Agent</div><h1 class="mmca-title">やさしく見守る、サイト健康チェック</h1><p class="mmca-muted">難しい設定画面ではなく、今の状態と次の一歩だけを分かりやすくお伝えします。</p></div></div>';

        if (!empty($context['notice']['message'])) {
            echo '<div class="notice notice-' . esc_attr($context['notice']['type'] === 'error' ? 'error' : 'success') . ' is-dismissible"><p>' . esc_html($context['notice']['message']) . '</p></div>';
        }

        switch ($state) {
            case 'welcome':
                $this->render_welcome_state($context);
                break;
            case 'waiting':
                $this->render_waiting_state($context);
                break;
            case 'connected':
                $this->render_connected_state($context);
                break;
            case 'local_only':
                $this->render_local_only_state($context);
                break;
            case 'not_approved':
                $this->render_not_approved_state($context);
                break;
            case 'error_retry':
                $this->render_error_retry_state($context);
                break;
            case 'result':
            default:
                $this->render_result_state($context);
                break;
        }

        $this->render_history_section($context['rows']);
        $this->render_technical_details_section($context);

        echo '</div></div>';
    }

    public function render_settings() {
        if (!current_user_can('manage_options')) return;
        ?>
        <div class="wrap mmca-shell">
            <div class="mmca-surface">
                <h1>みまもりエージェント 詳細設定</h1>
                <p class="mmca-muted">通常はこの画面を触らなくてもご利用いただけます。設定変更が必要な場合のみご利用ください。</p>
                <form method="post" action="options.php" class="mmca-card">
                    <?php settings_fields('mmca_settings'); ?>
                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row"><label for="mmca_hq_base_url">みまもりポータルURL</label></th>
                            <td>
                                <input name="mmca_hq_base_url" id="mmca_hq_base_url" type="url" class="regular-text" value="<?php echo esc_attr((string) get_option('mmca_hq_base_url', 'https://mimamori-wp.com/wp-json/mchq/v1')); ?>" />
                                <p class="description">通常は変更不要です。みまもりポータルの公開申請と正式連携の両方で利用します。</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="mmca_hq_token">みまもりポータル確認キー</label></th>
                            <td>
                                <input name="mmca_hq_token" id="mmca_hq_token" type="text" class="regular-text" value="<?php echo esc_attr((string) get_option('mmca_hq_token', '')); ?>" />
                                <p class="description">承認後に自動で受け取る確認キーです。通常は手動入力の必要はありません。</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">運用モード</th>
                            <td>
                                <label><input type="radio" name="mmca_mode" value="local_only" <?php checked(get_option('mmca_mode', 'local_only'), 'local_only'); ?> /> ローカル限定</label><br />
                                <span class="description">このサイト内だけで診断を続けます。JSON出力で本部にお渡しできます。</span><br /><br />
                                <label><input type="radio" name="mmca_mode" value="hq_required" <?php checked(get_option('mmca_mode', 'local_only'), 'hq_required'); ?> /> みまもりポータル連携を利用する</label><br />
                                <span class="description">みまもりポータルでの継続見守りやレポート連携を使う場合はこちらです。</span>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="mmca_requester_name">ご担当者名</label></th>
                            <td><input name="mmca_requester_name" id="mmca_requester_name" type="text" class="regular-text" value="<?php echo esc_attr((string) get_option('mmca_requester_name', '')); ?>" /></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="mmca_requester_email">ご担当者メール</label></th>
                            <td><input name="mmca_requester_email" id="mmca_requester_email" type="email" class="regular-text" value="<?php echo esc_attr((string) get_option('mmca_requester_email', '')); ?>" /></td>
                        </tr>
                        <tr>
                            <th scope="row">WordPressダッシュボード表示</th>
                            <td>
                                <label><input type="radio" name="mmca_show_dashboard_widget" value="yes" <?php checked(get_option('mmca_show_dashboard_widget', 'yes'), 'yes'); ?> /> 表示する</label><br />
                                <label><input type="radio" name="mmca_show_dashboard_widget" value="no" <?php checked(get_option('mmca_show_dashboard_widget', 'yes'), 'no'); ?> /> 表示しない</label>
                            </td>
                        </tr>
                    </table>
                    <?php submit_button('設定を保存'); ?>
                </form>
            </div>
        </div>
        <?php
    }
}
