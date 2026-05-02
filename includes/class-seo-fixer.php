<?php
/**
 * Hardens SEO for a headless setup by preventing backend indexing 
 * and ensuring canonicals point to the frontend.
 */
class Blog_Fetcher_SEO_Fixer
{
    public function __construct()
    {
        // 1. Overwrite Canonical URLs (Native WP and Rank Math)
        add_filter('get_canonical_url', array($this, 'get_headless_canonical'), 20, 2);
        add_filter('rank_math/frontend/canonical', array($this, 'get_rank_math_canonical'), 20);

        // 2. Add Noindex to Backend Post Views
        add_action('wp_head', array($this, 'add_noindex_meta'), 5);

        // 3. Redirect backend single views to frontend (optional safety)
        add_action('template_redirect', array($this, 'redirect_to_frontend'));

        // 4. Block Rank Math Instant Indexing for 3rd Party Posts
        add_filter('instant_indexing/publish_url', array($this, 'block_rank_math_indexing'), 10, 2);
    }

    /**
     * Helper to get the mapped frontend URL for a post.
     */
    private function get_frontend_post_url($post_id)
    {
        $platforms = get_option('blog_fetcher_platforms', array());
        if (empty($platforms)) {
            return false;
        }

        $post = get_post($post_id);
        if (!$post || $post->post_type !== 'post') {
            return false;
        }

        // Get the assigned platform for this post
        $assigned_platform_slug = get_post_meta($post_id, '_blog_fetcher_platform', true);
        if (empty($assigned_platform_slug)) {
            return false;
        }

        // Find the platform URL
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

        return rtrim($platform_url, '/') . '/blog/' . $post->post_name;
    }

    /**
     * Overwrites native WP canonical.
     */
    public function get_headless_canonical($canonical_url, $post)
    {
        if (is_singular('post')) {
            $mapped_url = $this->get_frontend_post_url($post->ID);
            if ($mapped_url) {
                return $mapped_url;
            }
        }
        return $canonical_url;
    }

    /**
     * Overwrites Rank Math canonical.
     */
    public function get_rank_math_canonical($canonical)
    {
        if (is_singular('post')) {
            $mapped_url = $this->get_frontend_post_url(get_the_ID());
            if ($mapped_url) {
                return $mapped_url;
            }
        }
        return $canonical;
    }

    /**
     * Adds noindex to backend views.
     */
    public function add_noindex_meta()
    {
        if (is_singular('post')) {
            $platform_type = get_post_meta(get_the_ID(), '_blog_fetcher_platform_type', true);
            if ($platform_type !== 'default' && !empty($platform_type)) {
                echo '<meta name="robots" content="noindex, nofollow" />' . "\n";
            }
        }
    }

    /**
     * Redirects backend single views to the frontend.
     */
    public function redirect_to_frontend()
    {
        if (is_singular('post') && !is_user_logged_in()) {
            $mapped_url = $this->get_frontend_post_url(get_the_ID());
            if ($mapped_url) {
                wp_redirect($mapped_url, 301);
                exit;
            }
        }
    }

    /**
     * Blocks Rank Math from auto-indexing 3rd party posts.
     * These posts are indexed via our custom GSC logic with the correct frontend URL.
     */
    public function block_rank_math_indexing($url, $post)
    {
        $platform_type = get_post_meta($post->ID, '_blog_fetcher_platform_type', true);
        if ($platform_type === '3rd_party') {
            return false; // Prevent Rank Math from submitting the native WP URL
        }
        return $url;
    }
}
