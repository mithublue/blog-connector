<?php
/**
 * Plugin Name:       Blog Fetcher
 * Description:       Exposes specific blog posts via REST API for headless frontends (e.g. Verihuman, CyberCraft Blog), filtering them from the main WP frontend. Includes comprehensive AEO/SEO support via RankMath or generic fallbacks.
 * Version:           1.0.0
 * Author:            Your Name
 * License:           GPL-2.0+
 * Text Domain:       blog-fetcher
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
	die;
}

/**
 * The core plugin class.
 */
class Blog_Fetcher
{

	public function __construct()
	{
		$this->load_dependencies();
		$this->init_hooks();
	}

	private function load_dependencies()
	{
		require_once plugin_dir_path(__FILE__) . 'includes/class-settings.php';
		require_once plugin_dir_path(__FILE__) . 'includes/class-meta-boxes.php';
		require_once plugin_dir_path(__FILE__) . 'includes/class-frontend-filter.php';
		require_once plugin_dir_path(__FILE__) . 'includes/class-seo-handler.php';
		require_once plugin_dir_path(__FILE__) . 'includes/class-rest-api.php';
		require_once plugin_dir_path(__FILE__) . 'includes/class-seo-fixer.php';
		require_once plugin_dir_path(__FILE__) . 'includes/class-gsc-indexer.php';
		require_once plugin_dir_path(__FILE__) . 'includes/class-admin-list.php';
	}

	private function init_hooks()
	{
		$settings = new Blog_Fetcher_Settings();
		add_action('admin_menu', array($settings, 'add_settings_menu'));

		$meta_boxes = new Blog_Fetcher_Meta_Boxes();
		add_action('add_meta_boxes', array($meta_boxes, 'add_meta_boxes'));
		add_action('save_post', array($meta_boxes, 'save_meta_boxes'));
		// Enqueue scripts/styles for our custom metaboxes if needed
		add_action('admin_enqueue_scripts', array($meta_boxes, 'enqueue_admin_assets'));

		$frontend_filter = new Blog_Fetcher_Frontend_Filter();
		add_action('pre_get_posts', array($frontend_filter, 'filter_posts'));

		$rest_api = new Blog_Fetcher_REST_API();
		add_action('rest_api_init', array($rest_api, 'register_routes'));

		new Blog_Fetcher_SEO_Fixer();
		new Blog_Fetcher_GSC_Indexer();
		new Blog_Fetcher_Admin_List();
	}
}

/**
 * Initialize the plugin.
 */
function run_blog_fetcher()
{
	new Blog_Fetcher();
}
run_blog_fetcher();
