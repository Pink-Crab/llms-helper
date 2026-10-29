=== LLMs Helper ===
Contributors: pinkcrab
Tags: llms.txt, markdown, ai, llm, agents
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 8.0
Stable tag: 0.1.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.txt

Static Markdown copies of your content and an llms.txt, served at the llmstxt.org URLs.

== Description ==

LLMs Helper writes a Markdown copy of each published post and page to uploads when it is saved, and an llms.txt listing them. Agents can read a page without loading it in full.

* Copies are served at the llmstxt.org URLs: `/about/` has its copy at `/about/index.html.md`.
* `/llms.txt` lists the site name, a summary, your notes, and a section per post type linking each copy.
* Each copy starts with front matter (title, URL, dates, author, excerpt, featured image, terms) followed by the content as Markdown, with full URLs for links, images and embeds.
* Files are written on save and removed on unpublish, trash or delete. Nothing is converted when a copy is requested.
* Editing or deleting a category, tag or other term queues the copies of its posts to be built again.
* Redirect mode (on by default) sends a 301 from `/llms.txt` and the `.md` URLs to the files in uploads, for hosts where the site root cannot be written to. With it off, copies are sent by PHP at their own URL and llms.txt is written to the site root.
* A `.md` URL with no copy renders the page it belongs to, or sends a plain 404 or 410.
* Choose post types in Settings > LLMs Helper and exclude single posts from the editor.
* A post's Markdown override, in the editor's Markdown copy box, is used for its copy in place of the content.
* The meta description Yoast, Rank Math, SEOPress, AIOSEO or Slim SEO prints for a post goes in its copy's front matter and after its link in llms.txt. With no llms.txt summary set, Yoast's or Rank Math's home page description is used before the tagline.
* Posts marked noindex by Yoast, Rank Math, SEOPress, AIOSEO or Slim SEO, on the post or for its post type, get no copy and are left out of llms.txt.
* llms.txt is for agents rather than search results, so when PHP sends it, it has an `X-Robots-Tag: noindex` header. Turn on "Let search engines index llms.txt" in Settings > LLMs Helper to drop the header and list llms.txt in the Yoast SEO or Rank Math sitemap index, as `llms-sitemap.xml`, dated when llms.txt was last built.
* Posts that Paid Memberships Pro, Members, Content Control, Ultimate Member, Restrict Content, LifterLMS or WooCommerce Memberships keep from logged out visitors get no copy, since copies are public files.
* Copies are rendered by requesting the post's own page as a logged out visitor, so members only shortcodes and blocks give what a visitor sees. A site that cannot reach its own address renders them in the build's request instead; the `llms_helper_render_request` filter changes the request's URL and arguments.
* When a copy or llms.txt changes, its URLs are cleared from WP Rocket, W3 Total Cache, WP Super Cache, WP Fastest Cache and LiteSpeed Cache, and passed to the `llms_helper_purge_urls` action for anything else, such as a CDN.
* A site closed to visitors gives no copies: Members' private site, Ultimate Member's site for logged in users only (except the pages it leaves open), and WooCommerce's coming soon mode for the whole site, or for products and store pages when only the store is coming soon.
* WooCommerce: products hidden from the shop or search results get no copy, nor do the cart, checkout and my account pages. Product copies are built once WooCommerce has saved the product's terms and meta, and their front matter has the price (or the regular and sale price, or a variation range), currency, SKU and stock status a visitor sees.
* Changing the post types queues a build of the copies. Changing the summary, notes or redirect mode only rewrites llms.txt. A build skips copies already up to date.
* Background work runs through Action Scheduler when it is installed, otherwise WP-Cron. A batch that dies, from a fatal error or a timeout, runs again up to three times, and Settings > LLMs Helper says when a build stopped or when the jobs are overdue because WP-Cron is not running.

= WP-CLI =

* `wp llms-helper build` builds the copies of the selected post types and llms.txt.
* `wp llms-helper build 12 34` builds the copies of those posts only.
* `wp llms-helper index` builds llms.txt only.

= Filters =

* `llms_helper_include_post` (bool $included, WP_Post $post) decides whether a post gets a copy.
* `llms_helper_front_matter` (array $data, WP_Post $post) changes the front matter of a copy.
* `llms_helper_convert_html` (string $html, string $base_url) changes the HTML before it is converted.
* `llms_helper_convert_markdown` (string $markdown, string $html, string $base_url) changes the Markdown after conversion.
* `llms_helper_convert_element` (string|null $markdown, DOMElement $element, string $context, Html_Converter $converter) converts one element yourself; return null to leave it to the plugin. `$context` is `block` or `inline`.
* `llms_helper_skip_elements` (array $tags) elements left out of copies.
* `llms_helper_inline_elements` (array $tags) elements treated as inline text.
* `llms_helper_render_request` (array $request, WP_Post $post) changes the request to the post's page its copy is rendered from: `url`, and `args` for `wp_remote_get()`. For sites that cannot reach their own public address.
* `llms_helper_use_action_scheduler` (bool $use) set false to use WP-Cron when Action Scheduler is installed.
* `llms_helper_controllers` (array $controllers) changes the controller classes booted.
* `llms_helper_di_rules` (array $rules) changes which class each interface resolves to. For example, binding `PinkCrab\LLMs_Helper\Interfaces\Content_Renderer` to `PinkCrab\LLMs_Helper\Content\Feed_Content_Renderer` builds copies from the content as the site's feeds show it, for sites whose plugins clean their feed output. Hook it from an mu-plugin, as the plugin reads it when it loads.

= Actions =

* `llms_helper_build_post` (int $post_id) do this to build or remove one post's copy, for example after changing data the copy is made from without saving the post.
* `llms_helper_purge_urls` (array $urls) fires with the URLs to clear from caches after a copy or llms.txt changes.

== Changelog ==

= 0.1.0-rc.1 =
* First release candidate.

= 0.1.0 =
* First release.
