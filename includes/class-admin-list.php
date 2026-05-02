<?php
/**
 * Handles the admin post list columns and filters.
 */
class Blog_Fetcher_Admin_List
{
    public function __construct()
    {
        // Add columns to post list
        add_filter('manage_post_posts_columns', array($this, 'add_indexing_columns'));
        add_action('manage_post_posts_custom_column', array($this, 'render_indexing_column'), 10, 2);
        
        // Add sorting
        add_filter('manage_edit-post_sortable_columns', array($this, 'make_indexing_column_sortable'));
        
        // Add filter dropdowns
        add_action('restrict_manage_posts', array($this, 'add_admin_filters'));
        add_action('pre_get_posts', array($this, 'apply_admin_filters'));
    }

    /**
     * Add "Indexing Status" column.
     */
    public function add_indexing_columns($columns)
    {
        // Insert before Date
        $new_columns = array();
        foreach ($columns as $key => $value) {
            if ($key === 'date') {
                $new_columns['bf_platform'] = __('Platform', 'blog-fetcher');
                $new_columns['bf_gsc_status'] = __('GSC Indexing', 'blog-fetcher');
            }
            $new_columns[$key] = $value;
        }
        return $new_columns;
    }

    /**
     * Render the column content.
     */
    public function render_indexing_column($column, $post_id)
    {
        if ($column === 'bf_platform') {
            $platform_slug = get_post_meta($post_id, '_blog_fetcher_platform', true);
            if (empty($platform_slug)) {
                echo '<span style="color:#ccc;">—</span>';
            } else {
                $platforms = get_option('blog_fetcher_platforms', array());
                $name = $platform_slug;
                foreach ($platforms as $p) {
                    if ($p['slug'] === $platform_slug) {
                        $name = $p['name'];
                        break;
                    }
                }
                echo '<strong>' . esc_html($name) . '</strong>';
            }
        }

        if ($column === 'bf_gsc_status') {
            $status = get_post_meta($post_id, '_blog_fetcher_gsc_status', true);
            $platform_type = get_post_meta($post_id, '_blog_fetcher_platform_type', true);
            
            echo '<div class="bf-gsc-column-content" id="bf-gsc-status-' . $post_id . '">';
            if (empty($status)) {
                echo '<span style="color:#999; display: flex; align-items: center; gap: 5px;"><span class="dashicons dashicons-minus"></span> ' . __('Not Submitted', 'blog-fetcher') . '</span>';
            } elseif (strpos($status, 'Success') !== false) {
                echo '<span style="color:#46b450; font-weight: 500; display: flex; align-items: center; gap: 5px;"><span class="dashicons dashicons-yes-alt"></span> ' . __('Submitted', 'blog-fetcher') . '</span>';
                $date = str_replace('Success: Last indexed at ', '', $status);
                echo '<div style="font-size: 10px; color: #666; margin-top: 2px;">' . esc_html($date) . '</div>';
            } elseif (strpos($status, 'Error') !== false) {
                echo '<span style="color:#d63638; font-weight: 500; display: flex; align-items: center; gap: 5px;"><span class="dashicons dashicons-warning"></span> ' . __('Failed', 'blog-fetcher') . '</span>';
                $err = substr($status, 7);
                echo '<div style="font-size: 10px; color: #d63638; margin-top: 2px;" title="' . esc_attr($err) . '">' . esc_html(wp_trim_words($err, 5)) . '</div>';
            } else {
                echo '<span style="color:#2271b1; display: flex; align-items: center; gap: 5px;"><span class="dashicons dashicons-update spin"></span> ' . esc_html($status) . '</span>';
            }

            // Add Index button if it's a 3rd party platform
            if ($platform_type === '3rd_party') {
                echo '<div style="margin-top: 5px;">';
                echo '<a href="#" class="bf-list-index-btn" data-post-id="' . $post_id . '" style="text-decoration:none; font-size:11px; background:#2271b1; color:#fff; padding:2px 6px; border-radius:3px; display:inline-flex; align-items:center; gap:3px;">';
                echo '<span class="dashicons dashicons-cloud" style="font-size:14px; width:14px; height:14px;"></span> ' . __('Index Now', 'blog-fetcher');
                echo '</a>';
                echo '<span class="spinner bf-list-spinner" style="float:none; margin:0 0 0 5px;"></span>';
                echo '</div>';
            }
            echo '</div>';
        }
    }

    /**
     * Make the column sortable.
     */
    public function make_indexing_column_sortable($columns)
    {
        $columns['bf_gsc_status'] = 'bf_gsc_status';
        $columns['bf_platform'] = 'bf_platform';
        return $columns;
    }

    /**
     * Add filter dropdowns to post list.
     */
    public function add_admin_filters($post_type)
    {
        if ($post_type !== 'post') {
            return;
        }

        // Platform Filter
        $platforms = get_option('blog_fetcher_platforms', array());
        $current_p = $_GET['bf_platform_filter'] ?? '';
        ?>
        <select name="bf_platform_filter">
            <option value=""><?php _e('All Platforms', 'blog-fetcher'); ?></option>
            <?php foreach ($platforms as $p): ?>
                <option value="<?php echo esc_attr($p['slug']); ?>" <?php selected($current_p, $p['slug']); ?>>
                    <?php echo esc_html($p['name']); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <?php
        // GSC Status Filter
        $current_v = $_GET['bf_gsc_filter'] ?? '';
        ?>
        <select name="bf_gsc_filter">
            <option value=""><?php _e('All GSC Status', 'blog-fetcher'); ?></option>
            <option value="indexed" <?php selected($current_v, 'indexed'); ?>><?php _e('Submitted', 'blog-fetcher'); ?></option>
            <option value="failed" <?php selected($current_v, 'failed'); ?>><?php _e('Failed', 'blog-fetcher'); ?></option>
            <option value="not_submitted" <?php selected($current_v, 'not_submitted'); ?>><?php _e('Not Submitted', 'blog-fetcher'); ?></option>
        </select>

        <style>
            .column-bf_gsc_status { width: 160px; }
            .column-bf_platform { width: 120px; }
            @keyframes bf-spin {
                from { transform: rotate(0deg); }
                to { transform: rotate(360deg); }
            }
            .dashicons-update.spin {
                animation: bf-spin 2s infinite linear;
            }
            .bf-list-spinner { visibility: hidden; }
            .bf-list-spinner.is-active { visibility: visible; }
        </style>

        <script>
            jQuery(document).ready(function ($) {
                $('.bf-list-index-btn').on('click', function (e) {
                    e.preventDefault();
                    var btn = $(this);
                    var postId = btn.data('post-id');
                    var container = $('#bf-gsc-status-' + postId);
                    var spinner = btn.next('.bf-list-spinner');

                    btn.css('pointer-events', 'none').css('opacity', '0.5');
                    spinner.addClass('is-active');

                    $.post(ajaxurl, {
                        action: 'bf_manual_index',
                        post_id: postId,
                        nonce: '<?php echo wp_create_nonce("bf_manual_index_nonce"); ?>'
                    }, function (response) {
                        spinner.removeClass('is-active');
                        btn.css('pointer-events', 'auto').css('opacity', '1');
                        
                        if (response.success) {
                            // Update the column content dynamically
                            location.reload(); // Quickest way to refresh all status
                        } else {
                            alert('Error: ' + response.data);
                        }
                    });
                });
            });
        </script>
        <?php
    }

    /**
     * Apply the filters to the query.
     */
    public function apply_admin_filters($query)
    {
        if (!is_admin() || !$query->is_main_query() || $query->get('post_type') !== 'post') {
            return;
        }

        $meta_query = (array) $query->get('meta_query');

        // Platform Filter
        $platform_filter = $_GET['bf_platform_filter'] ?? '';
        if (!empty($platform_filter)) {
            $meta_query[] = array(
                'key' => '_blog_fetcher_platform',
                'value' => $platform_filter,
                'compare' => '='
            );
        }

        // GSC Status Filter
        $gsc_filter = $_GET['bf_gsc_filter'] ?? '';
        if (!empty($gsc_filter)) {
            if ($gsc_filter === 'indexed') {
                $meta_query[] = array(
                    'key' => '_blog_fetcher_gsc_status',
                    'value' => 'Success',
                    'compare' => 'LIKE'
                );
            } elseif ($gsc_filter === 'failed') {
                $meta_query[] = array(
                    'key' => '_blog_fetcher_gsc_status',
                    'value' => 'Error',
                    'compare' => 'LIKE'
                );
            } elseif ($gsc_filter === 'not_submitted') {
                $meta_query[] = array(
                    'relation' => 'OR',
                    array(
                        'key' => '_blog_fetcher_gsc_status',
                        'compare' => 'NOT EXISTS'
                    ),
                    array(
                        'key' => '_blog_fetcher_gsc_status',
                        'value' => '',
                        'compare' => '='
                    )
                );
            }
        }

        if (!empty($meta_query)) {
            $query->set('meta_query', $meta_query);
        }

        // Handle Sorting
        $orderby = $query->get('orderby');
        if ($orderby === 'bf_gsc_status') {
            $query->set('meta_key', '_blog_fetcher_gsc_status');
            $query->set('orderby', 'meta_value');
        } elseif ($orderby === 'bf_platform') {
            $query->set('meta_key', '_blog_fetcher_platform');
            $query->set('orderby', 'meta_value');
        }
    }
}
