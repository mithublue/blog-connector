<?php
/**
 * Handles adding and saving custom meta boxes for the posts.
 */
class Blog_Fetcher_Meta_Boxes
{

    public function add_meta_boxes()
    {
        add_meta_box(
            'blog_fetcher_meta_box',
            __('Blog Fetcher: Platform & AEO Settings', 'blog-fetcher'),
            array($this, 'render_meta_box'),
            'post',
            'normal',
            'high'
        );
    }

    public function enqueue_admin_assets($hook)
    {
        global $post;

        if ($hook == 'post-new.php' || $hook == 'post.php') {
            if ('post' === $post->post_type) {
                // Style for tags and existing fields
                wp_add_inline_style('wp-admin', '
					.bf-field-group { margin-bottom: 20px; }
					.bf-field-group label { font-weight: bold; display: block; margin-bottom: 8px; font-size: 14px; }
					.bf-field-group select, .bf-field-group input[type="text"], .bf-field-group textarea { width: 100%; max-width: 100%; padding: 8px; }
					.bf-faq-item { background: #fff; border: 1px solid #ccd0d4; margin-bottom: 10px; box-shadow: 0 1px 1px rgba(0,0,0,.04); }
					.bf-faq-item input, .bf-faq-item textarea { width: 100%; margin-bottom: 8px; }
					.remove-faq { color: #d63638; cursor: pointer; text-decoration: none; font-size: 12px; }
                    .remove-faq:hover { color: #991114; }
                    
                    /* Tag Input Styles */
                    .bf-tag-container { display: flex; flex-wrap: wrap; gap: 8px; padding: 8px; border: 1px solid #8c8f94; border-radius: 4px; background: #fff; min-height: 40px; align-items: center; }
                    .bf-tag { display: flex; align-items: center; background: #2271b1; color: #fff; padding: 4px 10px; border-radius: 20px; font-size: 13px; line-height: 1; }
                    .bf-tag-remove { margin-left: 6px; cursor: pointer; font-weight: bold; font-size: 16px; opacity: 0.7; transition: opacity 0.2s; }
                    .bf-tag-remove:hover { opacity: 1; }
                    .bf-tag-input { border: none !important; box-shadow: none !important; width: auto !important; flex: 1; min-width: 120px; padding: 4px !important; margin: 0 !important; }
                    .bf-tag-input:focus { outline: none !important; }
				');

                wp_add_inline_script('jquery-core', '
					jQuery(document).ready(function($) {
                        // FAQ Logic
						$("#bf-add-faq").click(function(e) {
							e.preventDefault();
							var html = "<div class=\\"bf-faq-item\\">";
                            html += "<div class=\\"bf-faq-header\\" style=\\"display:flex; justify-content:space-between; align-items:center; cursor:pointer; padding:10px; background:#f6f7f7; border-bottom:1px solid #dcdcde;\\">";
                            html += "<strong>New FAQ</strong><span class=\\"dashicons dashicons-arrow-down\\"></span></div>";
							html += "<div class=\\"bf-faq-content\\" style=\\"padding:12px;\\">";
							html += "<label>Question</label><input type=\\"text\\" name=\\"_blog_fetcher_faq_q[]\\" class=\\"bf-q-input\\" value=\\"\\"/>";
							html += "<label>Answer</label><textarea name=\\"_blog_fetcher_faq_a[]\\" rows=\\"3\\"></textarea>";
							html += "<a href=\\"#\\" class=\\"remove-faq\\">" + "Remove FAQ" + "</a>";
							html += "</div></div>";
							$("#bf-faq-container").append(html);
						});
						
						$(document).on("click", ".remove-faq", function(e) {
							e.preventDefault();
							$(this).closest(".bf-faq-item").remove();
						});

                        // Tag Input Logic
                        function updateHiddenInput() {
                            var tags = [];
                            $(".bf-tag span:first-child").each(function() {
                                tags.push($(this).text().trim());
                            });
                            $("#_blog_fetcher_entities_hidden").val(tags.join(", "));
                        }

                        function addTag(text) {
                            text = text.trim();
                            if (!text) return;
                            
                            // Avoid duplicates
                            var exists = false;
                            $(".bf-tag span:first-child").each(function() {
                                if ($(this).text().toLowerCase() === text.toLowerCase()) exists = true;
                            });
                            if (exists) return;

                            var tagHtml = "<div class=\\"bf-tag\\"><span>" + text + "</span><span class=\\"bf-tag-remove\\">&times;</span></div>";
                            $(".bf-tag-input").before(tagHtml);
                            updateHiddenInput();
                        }

                        $(document).on("keydown", ".bf-tag-input", function(e) {
                            if (e.key === "," || e.key === "Enter") {
                                e.preventDefault();
                                addTag($(this).val());
                                $(this).val("");
                            } else if (e.key === "Backspace" && $(this).val() === "") {
                                $(".bf-tag").last().remove();
                                updateHiddenInput();
                            }
                        });

                        $(document).on("click", ".bf-tag-remove", function() {
                            $(this).closest(".bf-tag").remove();
                            updateHiddenInput();
                        });

                        // Focus input when clicking container
                        $(".bf-tag-container").on("click", function() {
                            $(".bf-tag-input").focus();
                        });
					});
				');
            }
        }
    }

    public function render_meta_box($post)
    {
        wp_nonce_field('blog_fetcher_save_meta', 'blog_fetcher_meta_nonce');

        $selected_type = get_post_meta($post->ID, '_blog_fetcher_platform_type', true) ?: 'default';
        $selected_platform = get_post_meta($post->ID, '_blog_fetcher_platform', true) ?: '';

        $platforms = get_option('blog_fetcher_platforms', array());

        $tldr = get_post_meta($post->ID, '_blog_fetcher_tldr', true);
        $faqs = get_post_meta($post->ID, '_blog_fetcher_faq', true);
        $entities = !empty($entities_str) ? array_map("trim", explode(",", $entities_str)) : array();
        $gsc_status = get_post_meta($post->ID, '_blog_fetcher_gsc_status', true);

        // Calculate Frontend URL
        $frontend_url = '';
        if (!empty($selected_platform)) {
            foreach ($platforms as $p) {
                if ($p['slug'] === $selected_platform) {
                    $platform_base = rtrim($p['url'] ?? '', '/');
                    if ($platform_base) {
                        $frontend_url = $platform_base . '/blog/' . $post->post_name;
                    }
                    break;
                }
            }
        }
        ?>
        <div class="bf-field-group" id="bf-gsc-status-wrapper"
            style="<?php echo ($selected_type === '3rd_party') ? '' : 'display:none;'; ?>">
            <label><?php _e('Google Search Console Indexing', 'blog-fetcher'); ?></label>
            <div id="bf-gsc-status-content" style="padding: 10px; background: #f0f0f1; border-left: 4px solid #2271b1; margin-bottom: 10px;">
                <strong><?php _e('Status:', 'blog-fetcher'); ?></strong> 
                <span id="bf-gsc-status-text"><?php echo esc_html($gsc_status ?: __('Not indexed yet.', 'blog-fetcher')); ?></span>
                <?php if ($frontend_url): ?>
                    <div style="margin-top: 8px; font-size: 11px; color: #666; word-break: break-all;">
                        <strong><?php _e('Target URL:', 'blog-fetcher'); ?></strong> 
                        <code id="bf-gsc-target-url"><?php echo esc_html($frontend_url); ?></code>
                    </div>
                <?php endif; ?>
            </div>
            <button type="button" id="bf-manual-index-btn" class="button button-secondary" 
                data-post-id="<?php echo $post->ID; ?>">
                <?php _e('Index on Google Now', 'blog-fetcher'); ?>
            </button>
            <span id="bf-gsc-loader" class="spinner" style="float: none; visibility: hidden;"></span>
            <p class="description"><?php _e('Manual trigger to notify Google about this post URL.', 'blog-fetcher'); ?></p>
        </div>

        <script>
            jQuery(document).ready(function ($) {
                var platformUrls = <?php echo json_encode(array_column($platforms, 'url', 'slug')); ?>;
                var postSlug = '<?php echo $post->post_name; ?>';

                $('#_blog_fetcher_platform').on('change', function () {
                    var slug = $(this).val();
                    var urlDisplay = $('#bf-gsc-target-url');
                    if (slug && platformUrls[slug]) {
                        var baseUrl = platformUrls[slug].replace(/\/+$/, '');
                        urlDisplay.text(baseUrl + '/blog/' + postSlug);
                        urlDisplay.parent().show();
                    } else {
                        urlDisplay.parent().hide();
                    }
                });

                $('#bf-manual-index-btn').on('click', function (e) {
                    e.preventDefault();
                    var btn = $(this);
                    var postId = btn.data('post-id');
                    var statusText = $('#bf-gsc-status-text');
                    var loader = $('#bf-gsc-loader');

                    btn.prop('disabled', true);
                    loader.css('visibility', 'visible');
                    statusText.text('Indexing...');

                    $.post(ajaxurl, {
                        action: 'bf_manual_index',
                        post_id: postId,
                        nonce: '<?php echo wp_create_nonce("bf_manual_index_nonce"); ?>'
                    }, function (response) {
                        loader.css('visibility', 'hidden');
                        btn.prop('disabled', false);
                        if (response.success) {
                            statusText.text(response.data);
                            statusText.parent().css('border-left-color', '#46b450');
                        } else {
                            statusText.text(response.data);
                            statusText.parent().css('border-left-color', '#d63638');
                        }
                    });
                });
            });
        </script>

        <div class="bf-field-group">
            <label for="_blog_fetcher_platform_type"><?php _e('Target Type', 'blog-fetcher'); ?></label>
            <select name="_blog_fetcher_platform_type" id="_blog_fetcher_platform_type">
                <option value="default" <?php selected($selected_type, 'default'); ?>>
                    <?php _e('Default (WordPress)', 'blog-fetcher'); ?>
                </option>
                <option value="3rd_party" <?php selected($selected_type, '3rd_party'); ?>>
                    <?php _e('3rd Party Platform', 'blog-fetcher'); ?>
                </option>
            </select>
        </div>

        <div class="bf-field-group" id="bf-platform-select-wrapper"
            style="<?php echo ($selected_type === '3rd_party') ? '' : 'display:none;'; ?>">
            <label for="_blog_fetcher_platform"><?php _e('Choose Platform', 'blog-fetcher'); ?></label>
            <select name="_blog_fetcher_platform" id="_blog_fetcher_platform">
                <option value=""><?php _e('-- Select Platform --', 'blog-fetcher'); ?></option>
                <?php foreach ($platforms as $p): ?>
                    <option value="<?php echo esc_attr($p['slug']); ?>" <?php selected($selected_platform, $p['slug']); ?>>
                        <?php echo esc_html($p['name']); ?> (<?php echo esc_html($p['domain']); ?>)
                    </option>
                <?php endforeach; ?>
            </select>
            <p class="description">
                <?php _e('If selected, this post will NOT appear on this site\'s frontend.', 'blog-fetcher'); ?>
            </p>
        </div>

        <script>
            jQuery(document).ready(function ($) {
                $('#_blog_fetcher_platform_type').on('change', function () {
                    if ($(this).val() === '3rd_party') {
                        $('#bf-platform-select-wrapper').show();
                        $('#bf-gsc-status-wrapper').show();
                    } else {
                        $('#bf-platform-select-wrapper').hide();
                        $('#bf-gsc-status-wrapper').hide();
                    }
                });
            });
        </script>

        <div class="bf-field-group">
            <label for="_blog_fetcher_tldr">
                <?php _e('TLDR (Too Long; Didn\'t Read)', 'blog-fetcher'); ?>
            </label>
            <textarea name="_blog_fetcher_tldr" id="_blog_fetcher_tldr" rows="4"><?php echo esc_textarea($tldr); ?></textarea>
            <p class="description">A short summary of this article, used for AEO and summary endpoints.</p>
        </div>

        <div class="bf-field-group">
            <label>
                <?php _e('Key Entities', 'blog-fetcher'); ?>
            </label>
            <div class="bf-tag-container">
                <?php foreach ($entities as $entity): ?>
                    <?php if (empty($entity))
                        continue; ?>
                    <div class="bf-tag">
                        <span><?php echo esc_html($entity); ?></span>
                        <span class="bf-tag-remove">&times;</span>
                    </div>
                <?php endforeach; ?>
                <input type="text" class="bf-tag-input" placeholder="<?php _e('Add entity...', 'blog-fetcher'); ?>" />
            </div>
            <input type="hidden" name="_blog_fetcher_entities" id="_blog_fetcher_entities_hidden"
                value="<?php echo esc_attr($entities_str); ?>" />
            <p class="description">
                <?php _e('Main subjects, brands, or products mentioned (comma or enter to add). Used for AEO.', 'blog-fetcher'); ?>
            </p>
        </div>

        <div class="bf-field-group">
            <label>
                <?php _e('Frequently Asked Questions (FAQ)', 'blog-fetcher'); ?>
            </label>
            <p class="description">These will be output as JSON-LD FAQ Schema in the REST API and can be consumed by the
                headless frontend.</p>

            <div id="bf-faq-container">
                <?php
                if (!empty($faqs) && is_array($faqs)) {
                    foreach ($faqs as $faq) {
                        ?>
                        <div class="bf-faq-item">
                            <div class="bf-faq-header"
                                style="display:flex; justify-content:space-between; align-items:center; cursor:pointer; padding:5px; background:#eee;">
                                <strong><?php echo esc_html($faq['q'] ?? 'New FAQ'); ?></strong>
                                <span class="dashicons dashicons-arrow-down"></span>
                            </div>
                            <div class="bf-faq-content" style="padding-top:10px;">
                                <label>Question</label>
                                <input type="text" name="_blog_fetcher_faq_q[]" class="bf-q-input"
                                    value="<?php echo esc_attr($faq['q'] ?? ''); ?>" />
                                <label>Answer</label>
                                <textarea name="_blog_fetcher_faq_a[]" rows="3"><?php echo esc_textarea($faq['a'] ?? ''); ?></textarea>
                                <a href="#" class="remove-faq">Remove FAQ</a>
                            </div>
                        </div>
                        <?php
                    }
                }
                ?>
            </div>

            <button id="bf-add-faq" class="button">
                <?php _e('Add FAQ', 'blog-fetcher'); ?>
            </button>
        </div>

        <script>
            jQuery(document).ready(function ($) {
                $(document).on('click', '.bf-faq-header', function () {
                    $(this).next('.bf-faq-content').slideToggle();
                    $(this).find('.dashicons').toggleClass('dashicons-arrow-down dashicons-arrow-up');
                });

                $(document).on('keyup', '.bf-q-input', function () {
                    $(this).closest('.bf-faq-item').find('.bf-faq-header strong').text($(this).val() || 'New FAQ');
                });
            });
        </script>

        <?php if (!class_exists('RankMath')): ?>
            <hr>
            <h4>
                <?php _e('Fallback SEO Settings', 'blog-fetcher'); ?>
            </h4>
            <p class="description">RankMath is not active. Use these fields for basic SEO output in the REST API.</p>
            <?php
            $seo_title = get_post_meta($post->ID, '_blog_fetcher_seo_title', true);
            $seo_desc = get_post_meta($post->ID, '_blog_fetcher_seo_desc', true);
            ?>
            <div class="bf-field-group">
                <label for="_blog_fetcher_seo_title">
                    <?php _e('SEO Title', 'blog-fetcher'); ?>
                </label>
                <input type="text" name="_blog_fetcher_seo_title" id="_blog_fetcher_seo_title"
                    value="<?php echo esc_attr($seo_title); ?>" />
            </div>
            <div class="bf-field-group">
                <label for="_blog_fetcher_seo_desc">
                    <?php _e('SEO Description', 'blog-fetcher'); ?>
                </label>
                <textarea name="_blog_fetcher_seo_desc" id="_blog_fetcher_seo_desc"
                    rows="3"><?php echo esc_textarea($seo_desc); ?></textarea>
            </div>
        <?php endif; ?>
    <?php
    }

    public function save_meta_boxes($post_id)
    {
        if (!isset($_POST['blog_fetcher_meta_nonce'])) {
            return;
        }

        if (!wp_verify_nonce($_POST['blog_fetcher_meta_nonce'], 'blog_fetcher_save_meta')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (isset($_POST['post_type']) && 'post' === $_POST['post_type']) {
            if (!current_user_can('edit_post', $post_id)) {
                return;
            }
        } else {
            return; // Only apply to posts
        }

        // Save Platform Type
        $type = isset($_POST['_blog_fetcher_platform_type']) ? sanitize_text_field($_POST['_blog_fetcher_platform_type']) : 'default';
        update_post_meta($post_id, '_blog_fetcher_platform_type', $type);

        // Save Platform
        if ($type === '3rd_party' && isset($_POST['_blog_fetcher_platform'])) {
            update_post_meta($post_id, '_blog_fetcher_platform', sanitize_text_field($_POST['_blog_fetcher_platform']));
        } else {
            update_post_meta($post_id, '_blog_fetcher_platform', '');
        }

        // Save TLDR
        if (isset($_POST['_blog_fetcher_tldr'])) {
            update_post_meta($post_id, '_blog_fetcher_tldr', sanitize_textarea_field(wp_unslash($_POST['_blog_fetcher_tldr'])));
        }

        // Save Entities
        if (isset($_POST['_blog_fetcher_entities'])) {
            update_post_meta($post_id, '_blog_fetcher_entities', sanitize_text_field(wp_unslash($_POST['_blog_fetcher_entities'])));
        }

        // Save FAQs
        if (isset($_POST['_blog_fetcher_faq_q']) && isset($_POST['_blog_fetcher_faq_a'])) {
            $qs = wp_unslash($_POST['_blog_fetcher_faq_q']);
            $as = wp_unslash($_POST['_blog_fetcher_faq_a']);

            $faqs = array();
            for ($i = 0; $i < count($qs); $i++) {
                if (!empty($qs[$i]) && !empty($as[$i])) {
                    $faqs[] = array(
                        'q' => sanitize_text_field($qs[$i]),
                        'a' => sanitize_textarea_field($as[$i]),
                    );
                }
            }
            update_post_meta($post_id, '_blog_fetcher_faq', $faqs);
        } else {
            delete_post_meta($post_id, '_blog_fetcher_faq');
        }

        // Save Fallback SEO
        if (isset($_POST['_blog_fetcher_seo_title'])) {
            update_post_meta($post_id, '_blog_fetcher_seo_title', sanitize_text_field($_POST['_blog_fetcher_seo_title']));
        }
        if (isset($_POST['_blog_fetcher_seo_desc'])) {
            update_post_meta($post_id, '_blog_fetcher_seo_desc', sanitize_textarea_field(wp_unslash($_POST['_blog_fetcher_seo_desc'])));
        }
    }
}
