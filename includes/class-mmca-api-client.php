<?php
if (!defined('ABSPATH')) exit;

class MMCA_API_Client {
    private $collector;
    private $db;

    public function __construct(MMCA_Collector $collector, MMCA_DB $db) {
        $this->collector = $collector;
        $this->db = $db;
    }

    private function base_url() {
        return untrailingslashit((string) get_option('mmca_hq_base_url', ''));
    }

    private function public_base_url() {
        $base = $this->base_url();
        if ($base === '') {
            return '';
        }
        if (strpos($base, '/mchq/v1') !== false) {
            return str_replace('/mchq/v1', '/mchq-public/v1', $base);
        }
        if (strpos($base, '/mchq-public/v1') !== false) {
            return $base;
        }
        return rtrim($base, '/') . '/mchq-public/v1';
    }

    private function token() {
        return (string) get_option('mmca_hq_token', '');
    }

    private function claim_key() {
        $claim_key = (string) get_option('mmca_claim_key', '');
        if ($claim_key === '') {
            $claim_key = wp_generate_password(48, false, false);
            update_option('mmca_claim_key', $claim_key, false);
        }
        return $claim_key;
    }

    private function decode_response($response) {
        if (is_wp_error($response)) {
            return new WP_Error('mmca_transport_error', $this->friendly_transport_error($response));
        }
        $code = wp_remote_retrieve_response_code($response);
        $raw = wp_remote_retrieve_body($response);
        $json = json_decode($raw, true);
        if ($code >= 400) {
            return new WP_Error('mmca_http_' . $code, $this->friendly_http_error($code, $json, $raw));
        }
        return is_array($json) ? $json : ['raw' => $raw];
    }

    private function friendly_transport_error(WP_Error $error) {
        $message = $error->get_error_message();
        if (strpos($message, 'cURL error 28') !== false || strpos($message, 'timed out') !== false) {
            return 'みまもりポータルからの返事に時間がかかっています。サイト自体はそのままご利用いただけるので、少し時間をおいてもう一度お試しください。';
        }
        if (strpos($message, 'resolve host') !== false || strpos($message, 'Could not resolve host') !== false) {
            return 'みまもりポータルのご案内先を確認できませんでした。ローカル診断はそのまま使えるので、設定を確認してからもう一度お試しください。';
        }
        return 'みまもりポータルとの通信を確認できませんでした。サイト自体に自動変更は入っていないので、そのままご利用いただけます。';
    }

    private function friendly_http_error($code, $json, $raw) {
        if ($code === 401 || $code === 403) {
            return 'みまもりポータル側で確認中の可能性があります。少し時間をおいてから、もう一度状態をご確認ください。';
        }
        if ($code === 404) {
            return 'みまもりポータル側でまだ確認情報が見つかっていません。診断後まもない場合は、少し時間をおいてからお試しください。';
        }
        if ($code >= 500) {
            return 'みまもりポータル側で一時的に確認しづらい状態です。サイト自体はそのまま使えるので、時間をおいてもう一度お試しください。';
        }

        $message = is_array($json) ? ($json['message'] ?? '') : '';
        if ($message !== '') {
            return sanitize_text_field($message);
        }

        if (is_string($raw) && $raw !== '') {
            return sanitize_text_field($raw);
        }

        return '通信時の確認が完了しませんでした。サイト自体はそのままご利用いただけます。';
    }

    private function signed_request($method, $path, array $payload = null) {
        $base = $this->base_url();
        $token = $this->token();
        if ($base === '' || $token === '') {
            return new WP_Error('mmca_missing_settings', 'まだ連携準備が完了していないため、先に状態確認または開始申請を行ってください。');
        }
        $body = $payload ? wp_json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '';
        $timestamp = (string) time();
        $signature = hash_hmac('sha256', $timestamp . '.' . $body, $token);
        $args = [
            'method' => strtoupper($method),
            'timeout' => 15,
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
                'X-Mimamori-Timestamp' => $timestamp,
                'X-Mimamori-Signature' => $signature,
            ],
            'sslverify' => true,
        ];
        if ($body !== '') {
            $args['body'] = $body;
        }
        return $this->decode_response(wp_remote_request($base . $path, $args));
    }

    private function public_request($method, $path, array $payload = null, array $headers = []) {
        $base = $this->public_base_url();
        if ($base === '') {
            return new WP_Error('mmca_missing_base_url', 'みまもりポータルのご案内先がまだ設定されていません。詳細設定をご確認ください。');
        }
        $body = $payload ? wp_json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '';
        $args = [
            'method' => strtoupper($method),
            'timeout' => 15,
            'headers' => $headers,
            'sslverify' => true,
        ];
        if ($body !== '') {
            $args['body'] = $body;
            $args['headers']['Content-Type'] = 'application/json';
        }
        return $this->decode_response(wp_remote_request($base . $path, $args));
    }

    public function request_connection() {
        $payload = $this->collector->collect_status();
        $payload['requested_mode'] = (string) get_option('mmca_mode', 'local_only');
        $payload['notes'] = 'クライアント側プラグインからの接続申請';
        $payload['claim_key'] = $this->claim_key();

        $response = $this->public_request('POST', '/connections/request', $payload);
        if (!is_wp_error($response)) {
            update_option('mmca_last_connection_status', (string) ($response['status'] ?? 'pending'));
            update_option('mmca_support_enabled', 'yes');
            update_option('mmca_last_connection_requested_at', current_time('mysql'));
        }
        return $response;
    }

    public function check_connection_status() {
        $uuid = (string) get_option('mmca_site_uuid', '');
        if ($uuid === '') {
            return new WP_Error('mmca_missing_uuid', 'まだこのサイトの確認情報が揃っていません。先に診断を行ってください。');
        }

        $use_public = ($this->token() === '');
        if ($use_public) {
            $res = $this->public_request('GET', '/connections/status/' . rawurlencode($uuid), null, [
                'X-Mimamori-Claim-Key' => $this->claim_key(),
            ]);
        } else {
            $res = $this->signed_request('GET', '/connections/status/' . rawurlencode($uuid));
        }

        if (!is_wp_error($res)) {
            $status = (string) ($res['status'] ?? 'unknown');
            update_option('mmca_last_connection_status', $status);
            update_option('mmca_last_connection_checked_at', current_time('mysql'));
            if ($status === 'approved' && !empty($res['site_token'])) {
                update_option('mmca_hq_token', sanitize_text_field((string) $res['site_token']), false);
                if (!empty($res['rest_base_url'])) {
                    update_option('mmca_hq_base_url', esc_url_raw((string) $res['rest_base_url']), false);
                }
                update_option('mmca_support_enabled', 'yes');
            }
        }
        return $res;
    }

    public function sync_now() {
        $status = (string) get_option('mmca_last_connection_status', 'not_requested');
        if ($status !== 'approved') {
            return new WP_Error('mmca_not_approved', 'みまもりポータルでの確認が完了していないため、まだ最新状態の送信は始められません。');
        }
        $payload = $this->collector->capture_snapshot();
        $insert_id = $this->db->insert_snapshot($payload, 'queued', 'status');
        $res = $this->signed_request('POST', '/sites', $payload);
        if (!is_wp_error($res)) {
            $this->db->mark_synced($insert_id);
        }
        return $res;
    }
}
