<?php
/**
 * Handles automatic Google Search Console Indexing.
 */
class Blog_Fetcher_GSC_Indexer
{
    public function __construct()
    {
        // Hook after meta data is saved
        add_action('save_post', array($this, 'maybe_index_post'), 25, 2);

        // AJAX for manual indexing
        add_action('wp_ajax_bf_manual_index', array($this, 'manual_index_ajax'));
    }

    /**
     * AJAX handler for manual indexing.
     */
    public function manual_index_ajax()
    {
        check_ajax_referer('bf_manual_index_nonce', 'nonce');

        $post_id = intval($_POST['post_id'] ?? 0);
        if (!$post_id || !current_user_can('edit_post', $post_id)) {
            wp_send_json_error('Invalid post ID or permissions.');
        }

        $url = $this->get_frontend_post_url($post_id);
        if (!$url) {
            wp_send_json_error('No frontend URL mapped for this post.');
        }

        $result = $this->index_url($url, $post_id);

        if (isset($result['error'])) {
            wp_send_json_error($result['error']);
        }

        wp_send_json_success('Successfully notified Google Indexing API.');
    }

    /**
     * Trigger indexing if the post is assigned to a 3rd party platform.
     */
    public function maybe_index_post($post_id, $post)
    {
        if ($post->post_type !== 'post') {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        $platform_type = get_post_meta($post_id, '_blog_fetcher_platform_type', true);
        if ($platform_type !== '3rd_party') {
            return;
        }

        $url = $this->get_frontend_post_url($post_id);
        if (!$url) {
            return;
        }

        $this->index_url($url);
    }

    /**
     * Get the mapped frontend URL for a post.
     */
    private function get_frontend_post_url($post_id)
    {
        $platforms = get_option('blog_fetcher_platforms', array());
        if (empty($platforms)) {
            return false;
        }

        $assigned_platform_slug = get_post_meta($post_id, '_blog_fetcher_platform', true);
        if (empty($assigned_platform_slug)) {
            return false;
        }

        $platform_url = '';
        foreach ($platforms as $p) {
            if ($p['slug'] === $assigned_platform_slug) {
                $platform_url = $p['url'] ?? '';
                break;
            }
        }

        if (empty($platform_url)) {
            return false;
        }

        $post = get_post($post_id);
        return rtrim($platform_url, '/') . '/blog/' . $post->post_name;
    }

    /**
     * Send indexing request to Google.
     */
    public function index_url($url, $post_id = 0)
    {
        $token = $this->get_google_access_token();
        if (!$token) {
            $msg = $this->last_error ?? 'Error: Failed to get access token.';
            error_log('Blog Fetcher GSC: ' . $msg);
            if ($post_id) {
                update_post_meta($post_id, '_blog_fetcher_gsc_status', $msg);
            }
            return array('error' => $msg);
        }

        $api_url = 'https://indexing.googleapis.com/v3/urlNotifications:publish';
        $body = json_encode(array(
            'url'  => $url,
            'type' => 'URL_UPDATED'
        ));

        $response = wp_remote_post($api_url, array(
            'headers' => array(
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $token
            ),
            'body' => $body
        ));

        if (is_wp_error($response)) {
            $msg = 'Error: ' . $response->get_error_message();
            error_log('Blog Fetcher GSC: ' . $msg);
            if ($post_id) {
                update_post_meta($post_id, '_blog_fetcher_gsc_status', $msg);
            }
            return array('error' => $msg);
        }

        $resp_body = wp_remote_retrieve_body($response);
        $data = json_decode($resp_body, true);

        if (isset($data['error'])) {
            $msg = 'GSC Error: ' . ($data['error']['message'] ?? 'Unknown error');
            error_log('Blog Fetcher GSC: ' . $msg);
            if ($post_id) {
                update_post_meta($post_id, '_blog_fetcher_gsc_status', $msg);
            }
            return array('error' => $msg);
        }

        error_log('Blog Fetcher GSC: Indexed URL: ' . $url . ' - Response: ' . $resp_body);
        if ($post_id) {
            update_post_meta($post_id, '_blog_fetcher_gsc_status', 'Success: Last indexed at ' . current_time('mysql'));
        }
        return array('success' => true);
    }

    /**
     * Get OAuth2 Access Token using stored service account JSON.
     */
    private function get_google_access_token()
    {
        $stored_json = get_option('blog_fetcher_google_service_account_json', '');

        if (empty($stored_json)) {
            $this->last_error = 'No credentials. Go to Settings → Blog Fetcher and paste your Service Account JSON.';
            error_log('Blog Fetcher GSC: ' . $this->last_error);
            return false;
        }

        $key_data = json_decode($stored_json, true);

        if (!$key_data || empty($key_data['client_email']) || empty($key_data['private_key'])) {
            $this->last_error = 'Invalid JSON in DB. json_decode error: ' . json_last_error_msg();
            error_log('Blog Fetcher GSC: ' . $this->last_error);
            return false;
        }

        $header = base64_url_encode(json_encode(array(
            'alg' => 'RS256',
            'typ' => 'JWT'
        )));

        $now = time();
        $payload = base64_url_encode(json_encode(array(
            'iss'   => $key_data['client_email'],
            'scope' => 'https://www.googleapis.com/auth/indexing',
            'aud'   => 'https://oauth2.googleapis.com/token',
            'iat'   => $now,
            'exp'   => $now + 3600
        )));

        $signature_data = $header . '.' . $payload;
        $signature = '';
        if (!openssl_sign($signature_data, $signature, $key_data['private_key'], 'SHA256')) {
            $this->last_error = 'openssl_sign failed. Private key may be invalid.';
            error_log('Blog Fetcher GSC: ' . $this->last_error);
            return false;
        }

        $jwt = $signature_data . '.' . base64_url_encode($signature);

        $response = wp_remote_post('https://oauth2.googleapis.com/token', array(
            'body' => array(
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion'  => $jwt
            )
        ));

        if (is_wp_error($response)) {
            $this->last_error = 'Step 4 FAIL: HTTP error: ' . $response->get_error_message();
            error_log('Blog Fetcher GSC: ' . $this->last_error);
            return false;
        }

        $resp_body = wp_remote_retrieve_body($response);
        $data = json_decode($resp_body, true);

        if (empty($data['access_token'])) {
            $this->last_error = 'Step 5 FAIL: No access_token in response. Google said: ' . $resp_body;
            error_log('Blog Fetcher GSC: ' . $this->last_error);
            return false;
        }

        return $data['access_token'];
    }
}

/**
 * Base64URL Encoding helper.
 */
if (!function_exists('base64_url_encode')) {
    function base64_url_encode($data)
    {
        return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($data));
    }
}
