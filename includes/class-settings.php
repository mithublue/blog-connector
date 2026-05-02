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
            $names = wp_unslash($_POST['platform_name'] ?? array());
            $domains = wp_unslash($_POST['platform_domain'] ?? array());
            $urls = wp_unslash($_POST['platform_url'] ?? array());
            $api_token = wp_unslash($_POST['api_token'] ?? '');

            $platforms = array();
            for ($i = 0; $i < count($names); $i++) {
                if (!empty($names[$i])) {
                    $platforms[] = array(
                        'name' => sanitize_text_field($names[$i]),
                        'domain' => sanitize_text_field($domains[$i]),
                        'url' => esc_url_raw($urls[$i] ?? ''),
                        'slug' => sanitize_title($names[$i]),
                    );
                }
            }
            update_option('blog_fetcher_platforms', $platforms);
            update_option('blog_fetcher_api_token', sanitize_text_field($api_token));

            // Save Google Credentials
            if (isset($_POST['google_client_email'])) {
                update_option('blog_fetcher_google_client_email', sanitize_email(wp_unslash($_POST['google_client_email'])));
            }
            if (isset($_POST['google_private_key'])) {
                // wp_unslash prevents WordPress from doubling backslashes on every save
                $raw_key = wp_unslash(trim($_POST['google_private_key']));
                update_option('blog_fetcher_google_private_key', $raw_key);
            }

            echo '<div class="updated"><p>Settings saved successfully.</p></div>';
        }

        $platforms = get_option('blog_fetcher_platforms', array());
        $api_token = get_option('blog_fetcher_api_token', '');
        $google_client_email = get_option('blog_fetcher_google_client_email', '');
        $google_private_key = get_option('blog_fetcher_google_private_key', '');
        ?>
        <div class="wrap">
            <h1>
                <?php _e('Blog Fetcher: Platforms Configuration', 'blog-fetcher'); ?>
            </h1>
            <p>
                <?php _e('Define the 3rd party platforms that can consume your blog posts.', 'blog-fetcher'); ?>
            </p>

            <form method="post" action="">
                <?php wp_nonce_field('bf_save_platforms_action', 'bf_platforms_nonce'); ?>

                <h2><?php _e('API Access Token', 'blog-fetcher'); ?></h2>
                <p><?php _e('Use this token to authenticate requests from the VeriHuman app.', 'blog-fetcher'); ?></p>
                <div style="margin-bottom: 30px; display: flex; gap: 10px; align-items: center;">
                    <input type="text" id="bf_api_token" name="api_token" value="<?php echo esc_attr($api_token); ?>"
                        class="regular-text" style="font-family: monospace;" readonly />
                    <button type="button" id="bf_generate_token"
                        class="button"><?php _e('Generate New Token', 'blog-fetcher'); ?></button>
                </div>

                <h2><?php _e('Connected Platforms', 'blog-fetcher'); ?></h2>
                <p><?php _e('Define the platforms that consume your content. Each platform can have its own Headless Frontend URL for SEO canonical mapping.', 'blog-fetcher'); ?></p>
                <table class="widefat fixed" style="margin-bottom: 20px;">
                    <thead>
                        <tr>
                            <th>
                                <?php _e('Platform Name', 'blog-fetcher'); ?>
                            </th>
                            <th>
                                <?php _e('Domain (optional)', 'blog-fetcher'); ?>
                            </th>
                            <th>
                                <?php _e('Headless Frontend URL (e.g. https://verihuman.xyz)', 'blog-fetcher'); ?>
                            </th>
                            <th style="width: 100px;">
                                <?php _e('Action', 'blog-fetcher'); ?>
                            </th>
                        </tr>
                    </thead>
                    <tbody id="bf-platforms-list">
                        <?php foreach ($platforms as $platform): ?>
                            <tr>
                                <td><input type="text" name="platform_name[]" value="<?php echo esc_attr($platform['name']); ?>"
                                        class="regular-text" /></td>
                                <td><input type="text" name="platform_domain[]" value="<?php echo esc_attr($platform['domain']); ?>"
                                        class="regular-text" /></td>
                                <td><input type="url" name="platform_url[]" value="<?php echo esc_url($platform['url'] ?? ''); ?>"
                                        class="regular-text" placeholder="https://..." /></td>
                                <td><button class="button bf-remove-row">
                                        <?php _e('Remove', 'blog-fetcher'); ?>
                                    </button></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($platforms)): ?>
                            <tr>
                                <td><input type="text" name="platform_name[]" value="" class="regular-text" /></td>
                                <td><input type="text" name="platform_domain[]" value="" class="regular-text" /></td>
                                <td><input type="url" name="platform_url[]" value="" class="regular-text" placeholder="https://..." /></td>
                                <td><button class="button bf-remove-row">
                                        <?php _e('Remove', 'blog-fetcher'); ?>
                                    </button></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>

                <button type="button" id="bf-add-platform" class="button">
                    <?php _e('Add Platform', 'blog-fetcher'); ?>
                </button>

                <h2 style="margin-top: 40px;"><?php _e('Google Indexing API Credentials', 'blog-fetcher'); ?></h2>
                <p><?php _e('Enter your Google Service Account credentials to enable automatic indexing.', 'blog-fetcher'); ?></p>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="google_client_email"><?php _e('Client Email', 'blog-fetcher'); ?></label></th>
                        <td>
                            <input type="email" name="google_client_email" id="google_client_email" 
                                value="<?php echo esc_attr($google_client_email); ?>" class="regular-text" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="google_private_key"><?php _e('Private Key', 'blog-fetcher'); ?></label></th>
                        <td>
                            <textarea name="google_private_key" id="google_private_key" rows="10" cols="50" 
                                class="large-text code" placeholder="-----BEGIN PRIVATE KEY-----\n...\n-----END PRIVATE KEY-----"><?php echo esc_textarea($google_private_key); ?></textarea>
                            <p class="description"><?php _e('Paste the entire "private_key" value from your JSON file here.', 'blog-fetcher'); ?></p>
                        </td>
                    </tr>
                </table>

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
