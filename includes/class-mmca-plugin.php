<?php
if (!defined('ABSPATH')) exit;

class MMCA_Plugin {
    public $db;
    public $collector;
    public $api;
    public $admin;

    public static function activate() {
        MMCA_DB::install();
        if (!get_option('mmca_site_uuid')) update_option('mmca_site_uuid', wp_generate_uuid4(), false);
        if (!get_option('mmca_claim_key')) update_option('mmca_claim_key', wp_generate_password(48, false, false), false);
        if (!get_option('mmca_hq_base_url')) update_option('mmca_hq_base_url', 'https://mimamori-wp.com/wp-json/mchq/v1', false);
        if (!get_option('mmca_mode')) update_option('mmca_mode', 'local_only', false);
        if (!get_option('mmca_show_dashboard_widget')) update_option('mmca_show_dashboard_widget', 'yes', false);
        if (!get_option('mmca_onboarding_completed')) update_option('mmca_onboarding_completed', 'no', false);
        if (!get_option('mmca_support_enabled')) update_option('mmca_support_enabled', 'no', false);
    }

    public function __construct() {
        $this->db = new MMCA_DB();
        $this->collector = new MMCA_Collector($this->db);
        $this->api = new MMCA_API_Client($this->collector, $this->db);
        $this->admin = new MMCA_Admin($this->collector, $this->api, $this->db);
        add_action('admin_post_mmca_capture_snapshot', [$this->admin, 'capture_snapshot']);
        add_action('admin_post_mmca_download_snapshot', [$this->admin, 'download_snapshot']);
        add_action('admin_post_mmca_send_connection_request', [$this->admin, 'send_connection_request']);
        add_action('admin_post_mmca_check_connection_status', [$this->admin, 'check_connection_status']);
        add_action('admin_post_mmca_sync_now', [$this->admin, 'sync_now']);
    }
}
