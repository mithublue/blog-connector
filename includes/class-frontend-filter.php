<?php
/**
 * Handles filtering posts on the native WordPress frontend.
 */
class Blog_Fetcher_Frontend_Filter
{

    /**
     * Hook into pre_get_posts to exclude non-default platform posts from native loops.
     * 
     * @param WP_Query $query The WP_Query instance (passed by reference).
     */
    public function filter_posts($query)
    {
        // Don't modify singular post queries. 
        // We want the post to resolve so that SEO_Fixer can handle redirects/canonicals.
        if (is_admin() || !$query->is_main_query() || $query->is_singular()) {
            return;
        }
        // Actually, let's exclude them even from single views via frontend unless it's default.

        $meta_query = $query->get('meta_query');
        if (!is_array($meta_query)) {
            $meta_query = array();
        }

        // We want to ONLY include posts that EITHER:
        // 1. Don't have the _blog_fetcher_platform_type key
        // 2. Or have the _blog_fetcher_platform_type key set to 'default'

        $meta_query[] = array(
            'relation' => 'OR',
            array(
                'key' => '_blog_fetcher_platform_type',
                'compare' => 'NOT EXISTS',
            ),
            array(
                'key' => '_blog_fetcher_platform_type',
                'value' => 'default',
                'compare' => '=',
            ),
        );

        $query->set('meta_query', $meta_query);
    }
}
