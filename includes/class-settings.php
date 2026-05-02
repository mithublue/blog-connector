<?php
/**
 * Handles the plugin settings page.
 */
class Blog_Fetcher_Settings
{

    public function add_settings_menu()
    {
        add_options_page(
            __('Blog Fetcher Settings', 'blog-fetcher'),
            __('Blog Fetcher', 'blog-fetcher'),
            'manage_options',
            'blog-fetcher-settings',
            array($this, 'render_settings_page')
        );
    }

    public function render_settings_page()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        if (isset($_POST['bf_save_platforms']) && check_admin_referer('bf_save_platforms_action', 'bf_platforms_nonce')) {
            $names     = wp_unslash($_POST['platform_name'] ?? array());
            $domains   = wp_unslash($_POST['platform_domain'] ?? array());
            $urls      = wp_unslash($_POST['platform_url'] ?? array());
            $api_token = wp_unslash($_POST['api_token'] ?? '');

            $platforms = array();
            for ($i = 0; $i < count($names); $i++) {
                if (!empty($names[$i])) {
                    $platforms[] = array(
                        'name'   => sanitize_text_field($names[$i]),
                        'domain' => sanitize_text_field($domains[$i]),
                        'url'    => esc_url_raw($urls[$i] ?? ''),
                        'slug'   => sanitize_title($names[$i]),
                    );
                }
            }
            update_option('blog_fetcher_platforms', $platforms);
            update_option('blog_fetcher_api_token', sanitize_text_field($api_token));

            // Save Google Service Account JSON
            // Save Google Service Account JSON (only if user actually pasted something)
            if (isset($_POST['google_service_account_json'])) {
                $raw_json = wp_unslash(trim($_POST['google_service_account_json']));
                if (!empty($raw_json)) {
                    $parsed = json_decode($raw_json, true);
                    if ($parsed && !empty($parsed['client_email']) && !empty($parsed['private_key'])) {
                        // Store as autoload=false for security
                        update_option('blog_fetcher_google_service_account_json', $raw_json, false);
                        echo '<div class="updated"><p>✅ Settings saved. Service Account: <strong>' . esc_html($parsed['client_email']) . '</strong></p></div>';
                    } else {
                        echo '<div class="error"><p>❌ Invalid JSON or missing required fields. Google credentials were NOT updated.</p></div>';
                    }
                } else {
                    // Textarea is empty — keep existing credentials, just save other settings
                    echo '<div class="updated"><p>Settings saved. Google credentials unchanged.</p></div>';
                }
            } else {
                echo '<div class="updated"><p>Settings saved successfully.</p></div>';
            }
        }

        $platforms           = get_option('blog_fetcher_platforms', array());
        $api_token           = get_option('blog_fetcher_api_token', '');
        $stored_json         = get_option('blog_fetcher_google_service_account_json', '');
        $parsed_json         = $stored_json ? json_decode($stored_json, true) : null;
        $service_account_email = $parsed_json['client_email'] ?? '';
        ?>
        <div class="wrap">
            <h1><?php _e('Blog Fetcher: Settings', 'blog-fetcher'); ?></h1>

            <form method="post" action="">
                <?php wp_nonce_field('bf_save_platforms_action', 'bf_platforms_nonce'); ?>

                <h2><?php _e('API Access Token', 'blog-fetcher'); ?></h2>
                <p><?php _e('Use this token to authenticate requests from the VeriHuman app.', 'blog-fetcher'); ?></p>
                <div style="margin-bottom: 30px; display: flex; gap: 10px; align-items: center;">
                    <input type="text" id="bf_api_token" name="api_token" value="<?php echo esc_attr($api_token); ?>"
                        class="regular-text" style="font-family: monospace;" readonly />
                    <button type="button" id="bf_generate_token" class="button">
                        <?php _e('Generate New Token', 'blog-fetcher'); ?>
                    </button>
                </div>

                <h2><?php _e('Connected Platforms', 'blog-fetcher'); ?></h2>
                <p><?php _e('Define the platforms that consume your content.', 'blog-fetcher'); ?></p>
                <table class="widefat fixed" style="margin-bottom: 20px;">
                    <thead>
                        <tr>
                            <th><?php _e('Platform Name', 'blog-fetcher'); ?></th>
                            <th><?php _e('Domain (optional)', 'blog-fetcher'); ?></th>
                            <th><?php _e('Headless Frontend URL', 'blog-fetcher'); ?></th>
                            <th style="width: 100px;"><?php _e('Action', 'blog-fetcher'); ?></th>
                        </tr>
                    </thead>
                    <tbody id="bf-platforms-list">
                        <?php foreach ($platforms as $platform): ?>
                            <tr>
                                <td><input type="text" name="platform_name[]" value="<?php echo esc_attr($platform['name']); ?>" class="regular-text" /></td>
                                <td><input type="text" name="platform_domain[]" value="<?php echo esc_attr($platform['domain']); ?>" class="regular-text" /></td>
                                <td><input type="url" name="platform_url[]" value="<?php echo esc_url($platform['url'] ?? ''); ?>" class="regular-text" placeholder="https://..." /></td>
                                <td><button class="button bf-remove-row"><?php _e('Remove', 'blog-fetcher'); ?></button></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($platforms)): ?>
                            <tr>
                                <td><input type="text" name="platform_name[]" value="" class="regular-text" /></td>
                                <td><input type="text" name="platform_domain[]" value="" class="regular-text" /></td>
                                <td><input type="url" name="platform_url[]" value="" class="regular-text" placeholder="https://..." /></td>
                                <td><button class="button bf-remove-row"><?php _e('Remove', 'blog-fetcher'); ?></button></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                <button type="button" id="bf-add-platform" class="button" style="margin-bottom: 30px;">
                    <?php _e('+ Add Platform', 'blog-fetcher'); ?>
                </button>

                <h2 style="margin-top: 20px;"><?php _e('Google Indexing API', 'blog-fetcher'); ?></h2>

                <?php if ($service_account_email): ?>
                    <div style="background: #f0fff4; border-left: 4px solid #46b450; padding: 10px 14px; margin-bottom: 16px;">
                        ✅ <strong><?php _e('Active Service Account:', 'blog-fetcher'); ?></strong>
                        <?php echo esc_html($service_account_email); ?>
                    </div>
                <?php endif; ?>

                <p>
                    <?php _e('Paste your entire Google Service Account JSON here. It will be stored securely.', 'blog-fetcher'); ?>
                    <br><em style="color:#666;"><?php _e('Leave blank to keep existing credentials.', 'blog-fetcher'); ?></em>
                </p>
                <textarea name="google_service_account_json" id="google_service_account_json"
                    rows="12" class="large-text code"
                    placeholder='{"type":"service_account","project_id":"...","client_email":"...","private_key":"-----BEGIN PRIVATE KEY-----\n...\n-----END PRIVATE KEY-----\n",...}'
                    style="font-size: 12px; font-family: monospace;"></textarea>
                <p class="description">
                    <?php _e('Do NOT paste the existing value back — just leave blank if you don\'t want to change it, or paste a new JSON to replace it.', 'blog-fetcher'); ?>
                </p>

                <p class="submit">
                    <input type="submit" name="bf_save_platforms" class="button button-primary"
                        value="<?php _e('Save Settings', 'blog-fetcher'); ?>" />
                </p>
            </form>
        </div>

        <script>
            jQuery(document).ready(function ($) {
                $('#bf_generate_token').on('click', function () {
                    var token = [...Array(32)].map(() => Math.floor(Math.random() * 16).toString(16)).join('');
                    $('#bf_api_token').val(token);
                });

                $('#bf-add-platform').on('click', function () {
                    var row = '<tr>' +
                        '<td><input type="text" name="platform_name[]" value="" class="regular-text" /></td>' +
                        '<td><input type="text" name="platform_domain[]" value="" class="regular-text" /></td>' +
                        '<td><input type="url" name="platform_url[]" value="" class="regular-text" placeholder="https://..." /></td>' +
                        '<td><button class="button bf-remove-row">Remove</button></td>' +
                        '</tr>';
                    $('#bf-platforms-list').append(row);
                });

                $(document).on('click', '.bf-remove-row', function (e) {
                    e.preventDefault();
                    $(this).closest('tr').remove();
                });
            });
        </script>
        <?php
    }
}
