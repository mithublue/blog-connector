<?php
/**
 * Register custom REST API endpoints.
 */
class Blog_Fetcher_REST_API
{

    public function register_routes()
    {
        register_rest_route('blog-fetcher/v1', '/posts', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_posts'),
            'permission_callback' => array($this, 'check_api_token'),
        ));

        register_rest_route('blog-fetcher/v1', '/posts/(?P<id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_post'),
            'permission_callback' => array($this, 'check_api_token'),
        ));
    }

    public function check_api_token(WP_REST_Request $request)
    {
        $saved_token = get_option('blog_fetcher_api_token', '');
        if (empty($saved_token)) {
            // Allow if no token is configured
            return true;
        }

        $auth_header = $request->get_header('authorization');
        if (empty($auth_header)) {
            return new WP_Error('missing_token', 'Authorization header is missing', array('status' => 401));
        }

        $token = str_replace('Bearer ', '', $auth_header);
        if ($token !== $saved_token) {
            return new WP_Error('invalid_token', 'Invalid authorization token', array('status' => 401));
        }

        return true;
    }

    public function get_posts(WP_REST_Request $request)
    {
        $platform = $request->get_param('platform');
        $page = $request->get_param('page') ?: 1;
        $per_page = $request->get_param('per_page') ?: 10;

        $args = array(
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => $per_page,
            'paged' => $page,
        );

        if (!empty($platform)) {
            $args['meta_query'] = array(
                array(
                    'key' => '_blog_fetcher_platform',
                    'value' => sanitize_text_field($platform),
                    'compare' => '=',
                ),
            );
        }

        $query = new WP_Query($args);
        $posts_data = array();

        foreach ($query->posts as $post) {
            // Get thumbnail
            $thumbnail_url = get_the_post_thumbnail_url($post->ID, 'large');
            $tldr = get_post_meta($post->ID, '_blog_fetcher_tldr', true);

            // Basic formatting for the list view
            $posts_data[] = array(
                'id' => $post->ID,
                'slug' => $post->post_name,
                'title' => get_the_title($post),
                'excerpt' => get_the_excerpt($post),
                'tldr' => $tldr,
                'thumbnail' => $thumbnail_url,
                'date' => $post->post_date,
            );
        }

        $response = rest_ensure_response(array(
            'platform' => $platform,
            'total' => $query->found_posts,
            'total_pages' => $query->max_num_pages,
            'current_page' => $page,
            'posts' => $posts_data,
        ));

        return $response;
    }

    public function get_post(WP_REST_Request $request)
    {
        $post_id = (int) $request->get_param('id');
        $post = get_post($post_id);

        if (empty($post) || 'post' !== $post->post_type || 'publish' !== $post->post_status) {
            return new WP_Error('post_not_found', 'Post not found or not published', array('status' => 404));
        }

        $seo_handler = new Blog_Fetcher_SEO_Handler();
        $seo_data = $seo_handler->get_seo_data($post_id);

        $tldr = get_post_meta($post_id, '_blog_fetcher_tldr', true);
        $entities = get_post_meta($post_id, '_blog_fetcher_entities', true);
        $platform = get_post_meta($post_id, '_blog_fetcher_platform', true) ?: 'default';
        $thumbnail_url = get_the_post_thumbnail_url($post_id, 'full');

        // Get author data
        $author_id = $post->post_author;
        $author_name = get_the_author_meta('display_name', $author_id);

        // Get categories
        $categories = wp_get_post_categories($post_id, array('fields' => 'all'));
        $cat_data = array();
        foreach ($categories as $cat) {
            $cat_data[] = array(
                'id' => $cat->term_id,
                'name' => $cat->name,
                'slug' => $cat->slug,
            );
        }

        // Apply the_content filters so shortcodes and embeds are processed
        $content = apply_filters('the_content', $post->post_content);

        $data = array(
            'id' => $post->ID,
            'slug' => $post->post_name,
            'platform' => $platform,
            'title' => get_the_title($post),
            'content' => $content,
            'tldr' => $tldr,
            'entities' => $entities,
            'thumbnail' => $thumbnail_url,
            'author' => $author_name,
            'categories' => $cat_data,
            'date' => $post->post_date,
            'modified' => $post->post_modified,
            'seo' => $seo_data,
        );

        return rest_ensure_response($data);
    }
}
