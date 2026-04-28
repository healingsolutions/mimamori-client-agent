<?php
if (!defined('ABSPATH')) exit;

class MMCA_DB {
    private $table;

    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'mmca_snapshots';
    }

    public static function install() {
        global $wpdb;
        $table = $wpdb->prefix . 'mmca_snapshots';
        $charset = $wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta("CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            snapshot_type VARCHAR(30) NOT NULL DEFAULT 'status',
            payload LONGTEXT NOT NULL,
            sync_status VARCHAR(20) NOT NULL DEFAULT 'local',
            created_at DATETIME NOT NULL,
            synced_at DATETIME NULL,
            PRIMARY KEY (id),
            KEY sync_status (sync_status),
            KEY created_at (created_at)
        ) {$charset};");
    }

    public function insert_snapshot(array $payload, $sync_status = 'local', $type = 'status') {
        global $wpdb;
        $wpdb->insert($this->table, [
            'snapshot_type' => $type,
            'payload' => wp_json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'sync_status' => $sync_status,
            'created_at' => current_time('mysql'),
            'synced_at' => null,
        ]);
        return (int) $wpdb->insert_id;
    }

    public function mark_synced($id) {
        global $wpdb;
        $wpdb->update($this->table, [
            'sync_status' => 'synced',
            'synced_at' => current_time('mysql'),
        ], ['id' => (int)$id]);
    }

    public function latest_snapshot_row() {
        global $wpdb;
        return $wpdb->get_row("SELECT * FROM {$this->table} ORDER BY id DESC LIMIT 1");
    }

    public function latest_snapshot_payload() {
        $row = $this->latest_snapshot_row();
        if (!$row) return null;
        return json_decode($row->payload, true);
    }

    public function recent_rows($limit = 20) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->table} ORDER BY id DESC LIMIT %d", $limit));
    }
}
