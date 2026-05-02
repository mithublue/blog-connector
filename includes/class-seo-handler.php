<?php
/**
 * Extracts and formats SEO metadata.
 */
class Blog_Fetcher_SEO_Handler
{

    /**
     * Get comprehensive SEO data for a post.
     *
     * @param int $post_id The post ID.
     * @return array Required SEO data.
     */
    public function get_seo_data($post_id)
    {
        $post = get_post($post_id);
        if (!$post) {
            return array();
        }

        $data = array(
            'title' => '',
            'description' => '',
            'og' => array(),
            'twitter' => array(),
            'schema' => array(),
        );

        if (class_exists('RankMath')) {
            $data = $this->get_rankmath_data($post_id, $post, $data);
        } else {
            $data = $this->get_fallback_data($post_id, $post, $data);
        }

        // Inject custom FAQ schema generated from our meta boxes
        $faqs = get_post_meta($post_id, '_blog_fetcher_faq', true) ?: array();
        $data['faq'] = $faqs; // Flat list for easy consumption

        $custom_schema = $this->get_faq_schema($post_id);
        if (!empty($custom_schema) && !empty($custom_schema['mainEntity'])) {
            $data['schema'][] = $custom_schema;
        }

        return $data;
    }

    private function get_rankmath_data($post_id, $post, $data)
    {
        $data['title'] = get_post_meta($post_id, 'rank_math_title', true);
        $data['description'] = get_post_meta($post_id, 'rank_math_description', true);

        if (empty($data['title'])) {
            $data['title'] = $post->post_title;
        }
        if (empty($data['description'])) {
            $data['description'] = wp_trim_words($post->post_content, 25);
        }

        // Rankmath replaces variables, unfortunately getting fully parsed variables externally
        // without mimicking the frontend can be tricky. We use simple replacements.
        $data['title'] = $this->replace_basic_vars($data['title'], $post);
        $data['description'] = $this->replace_basic_vars($data['description'], $post);

        $data['og'] = array(
            'title' => get_post_meta($post_id, 'rank_math_facebook_title', true) ?: $data['title'],
            'description' => get_post_meta($post_id, 'rank_math_facebook_description', true) ?: $data['description'],
            'image' => get_post_meta($post_id, 'rank_math_facebook_image', true) ?: get_the_post_thumbnail_url($post_id, 'full'),
            'url' => get_permalink($post_id),
            'type' => 'article',
            'locale' => get_locale(),
        );

        $data['og']['title'] = $this->replace_basic_vars($data['og']['title'], $post);
        $data['og']['description'] = $this->replace_basic_vars($data['og']['description'], $post);

        $data['twitter'] = array(
            'card' => 'summary_large_image',
            'title' => get_post_meta($post_id, 'rank_math_twitter_title', true) ?: $data['og']['title'],
            'description' => get_post_meta($post_id, 'rank_math_twitter_description', true) ?: $data['og']['description'],
            'image' => get_post_meta($post_id, 'rank_math_twitter_image', true) ?: $data['og']['image'],
        );

        return $data;
    }

    private function get_fallback_data($post_id, $post, $data)
    {
        $seo_title = get_post_meta($post_id, '_blog_fetcher_seo_title', true);
        $seo_desc = get_post_meta($post_id, '_blog_fetcher_seo_desc', true);

        $data['title'] = $seo_title ?: $post->post_title;
        $data['description'] = $seo_desc ?: wp_trim_words($post->post_content, 25);

        $data['og'] = array(
            'title' => $data['title'],
            'description' => $data['description'],
            'image' => get_the_post_thumbnail_url($post_id, 'full'),
            'url' => get_permalink($post_id),
            'type' => 'article',
            'locale' => get_locale(),
        );

        $data['twitter'] = array(
            'card' => 'summary_large_image',
            'title' => $data['title'],
            'description' => $data['description'],
            'image' => $data['og']['image'],
        );

        return $data;
    }

    private function replace_basic_vars($text, $post)
    {
        if (empty($text))
            return $text;

        $replacements = array(
            '%title%' => $post->post_title,
            '%excerpt%' => $post->post_excerpt ?: wp_trim_words($post->post_content, 25),
            '%sitename%' => get_bloginfo('name'),
            '%sitedesc%' => get_bloginfo('description'),
        );
        return str_replace(array_keys($replacements), array_values($replacements), $text);
    }

    private function get_faq_schema($post_id)
    {
        $faqs = get_post_meta($post_id, '_blog_fetcher_faq', true);
        if (empty($faqs) || !is_array($faqs)) {
            return array();
        }

        $schema = array(
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => array(),
        );

        foreach ($faqs as $faq) {
            if (!empty($faq['q']) && !empty($faq['a'])) {
                $schema['mainEntity'][] = array(
                    '@type' => 'Question',
                    'name' => $faq['q'],
                    'acceptedAnswer' => array(
                        '@type' => 'Answer',
                        'text' => $faq['a'],
                    ),
                );
            }
        }

        return $schema;
    }
}
