# TODO

Step 2, once the plugin works end to end. References are to website-llms-txt 8.6.3.

## Inclusion

- [x] Exclude noindex posts: Yoast, SEOPress, Rank Math, AIOSEO, Slim SEO, each by its own rule including post type defaults. nofollow does not exclude. (`Seo_Plugin_Noindex`)
- [x] WooCommerce: exclude products with `product_visibility` `exclude-from-search` or `exclude-from-catalog`, and build product copies after WooCommerce finishes saving. (`WooCommerce_Events`)
- [x] Post-level membership restrictions: Paid Memberships Pro, Restrict Content, WooCommerce Memberships, Members, Content Control, Ultimate Member, LifterLMS. (`Membership_Plugin_Access`)
- [x] Site-wide gates: Members private site, Ultimate Member logged-in only (keeping its open pages), WooCommerce coming soon for the whole site or only the store.

## Content

- [x] Gating shortcodes: copies render as a logged out visitor, so members only shortcodes give their visitor output. e2e covers Paid Memberships Pro, Members, Content Control, Ultimate Member, Restrict Content, LifterLMS and WooCommerce Memberships.
- [x] Copies render in a request of their own to the post's page, as a logged out visitor on the front end, so plugins that decide once per request who is viewing, or only filter on the front end, hide what they hide from visitors. Rendered in the build's request when the site cannot reach itself. e2e covers Content Control's block controls. (`Loopback_Content_Renderer`, `Render_Endpoint`)
- [x] Meta descriptions from Yoast, Rank Math, SEOPress, AIOSEO and Slim SEO in each copy's front matter and as its llms.txt note; the site description from Yoast or Rank Math as the llms.txt summary. (`Seo_Plugin_Description`)
- [x] WooCommerce price, sale price or variation range, currency, SKU and stock status in the front matter. (`Product_Front_Matter`)
- [x] Feed content source as a second `Content_Renderer`, chosen through `llms_helper_di_rules`. (`Feed_Content_Renderer`)

## Operations

- [x] Overlapping builds: the newest build wins, and a batch of an older build stops when it finishes. (`Scheduler::start_build()`)
- [x] Retry a batch that dies, up to three runs, then show the failed build; warn when the plugin's jobs are more than 15 minutes overdue, as WP-Cron is not running. (`Scheduler::queue_retry()`, `Scheduler::overdue()`)
- [x] Build affected copies when a term is edited or deleted, from the queue. (`Term_Events`)
- [x] Per-post Markdown override, a field in the editor box used for the copy in place of the content. (`Markdown_Formatter::body()`)
- [x] Per-post build: "Build copy now" in the editor box, `wp llms-helper build <id>...`, and `do_action( 'llms_helper_build_post', $post_id )`.
- [x] Clear changed copy and llms.txt URLs from WP Rocket, W3 Total Cache, WP Super Cache, WP Fastest Cache and LiteSpeed Cache, each by its own purge for one URL. None of them keeps llms.txt or copies: each only stores HTML pages. Autoptimize is not a page cache and leaves them alone. (`Cache_Plugin_Purger`)
- [x] llms.txt in the Yoast and Rank Math sitemap indexes, as `llms-sitemap.xml`, behind the "Let search engines index llms.txt" setting, off by default. Each plugin's cached sitemaps are cleared when llms.txt is rebuilt or the setting changes. (`Llms_Txt_Sitemap`, `Sitemap_Events`)
- [x] `X-Robots-Tag: noindex` on llms.txt when PHP sends it, unless search engines may index it. (`Route::with_noindex()`)

## Undecided

- [ ] Copy URL ending: `index.html.md` only, `.md`, or free text.
- [ ] llms.txt from Yoast or Rank Math as an `Index_Generator`.
