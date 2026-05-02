# Blog Fetcher for WordPress

**Blog Fetcher** is a powerful WordPress plugin designed for headless architectures (like Next.js, Nuxt, or Gatsby). it allows you to selectively expose blog posts to external platforms via a secure REST API while optionally hiding them from your primary WordPress frontend.

## Features

-   **Secure REST API**: Expose posts via customized endpoints with Bearer Token authentication.
-   **Platform-Based Filtering**: Distribute different posts to different platforms (e.g., VeriHuman, CyberCraft) using a simple dropdown.
-   **Advanced SEO/AEO Support**: Automatically exports SEO metadata, RankMath schemas, and custom AI-optimized TL;DRs.
-   **Frontend Hiding**: Posts marked for external platforms can be hidden from the main WordPress blog/archives to avoid SEO cannibalization.
-   **Plug-and-Play**: Minimal configuration required.

## Installation

1.  **Download/Clone**: Download the `blog-fetcher` folder.
2.  **Upload**: Upload the folder to your WordPress site's `/wp-content/plugins/` directory.
3.  **Activate**: Go to **WordPress Admin > Plugins** and click **Activate** on "Blog Fetcher".

## Configuration

### 1. API Token
To protect your data, go to **Settings > Blog Fetcher**.
-   Click **Generate New Token**.
-   Copy this token. You will need to provide this in your Next.js/Frontend environment as the `BLOG_API_TOKEN`.
-   Click **Save Platforms**.

### 2. Define Platforms
In the same settings page, you can define multiple platforms:
-   **Platform Name**: A human-readable name (e.g., "VeriHuman").
-   **Domain**: The domain of the frontend app (optional).
-   **Save**: Click **Save Platforms**.

## How to Use

### Marketing a Post for Fetching
1.  Open or create a Post in the WordPress editor.
2.  Look for the **Blog Fetcher Settings** meta box (usually on the right sidebar or below the content).
3.  **Target Platform**: Select which platform should "own" this post.
4.  **AI TL;DR**: (Optional) Add a concise summary for AI engines or quick lookups.
5.  **Publish/Update**: Save the post.

### Consuming the API

The plugin registers two main endpoints:

#### 1. List Posts
`GET /wp-json/blog-fetcher/v1/posts?platform={platform_slug}`
-   **Headers**: `Authorization: Bearer <your_token>`
-   **Params**:
    -   `platform`: (Required) The slug of the platform defined in settings.
    -   `page`: (Optional) For pagination.
    -   `per_page`: (Optional) Default 10.

#### 2. Get Single Post
`GET /wp-json/blog-fetcher/v1/posts/{post_id}`
-   **Headers**: `Authorization: Bearer <your_token>`

## Integration with `@cybercraftit/blog-fetcher-client`

This plugin is designed to work seamlessly with the `@cybercraftit/blog-fetcher-client` NPM package. 

Simply provide your WordPress URL and the generated Token to the client, and it will handle the aggregation and UI rendering automatically.

## Requirements
-   WordPress 5.8+
-   PHP 7.4+
-   (Optional) RankMath SEO for advanced schema support.

## License
GPL-2.0+
