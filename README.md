# LLMs Helper

Writes static Markdown copies of WordPress content and an `llms.txt` to uploads, served at the [llmstxt.org](https://llmstxt.org/) URLs (`/page/index.html.md` and `/llms.txt`). See `readme.txt` for what the plugin does.

## Requirements

* PHP 8.2+
* WordPress 6.0+

## Development

```bash
composer install
npm install
```

### PHPUnit

Runs against a MySQL database, using `wp-phpunit` and the WordPress copy `roots/wordpress` installs into `wordpress/`.

Copy `tests/.env_sample` to `tests/.env` and fill in a database the tests can empty. **Every table with the `wpphpunittests_` prefix is dropped.**

```bash
composer test
composer coverage           # HTML report in tests/.cache/coverage
```

### Mutation testing

[Infection](https://infection.github.io/). Slow, so it is run occasionally, one thread at a time:

```bash
composer mutation -- --threads=1    # reports in tests/.cache/infection
```

### Linting

```bash
composer lint:php           # PHPCS (src + tests) and PHPStan
composer format:php         # PHPCBF
```

### End-to-end

Playwright against a wp-env site on PHP 8.3 (ports 57891 and 57892). Needs Node 20 (`.nvmrc`), Docker, and `vendor/` from `composer install`.

```bash
nvm use
npx playwright install chromium
npm run wp-env:start
npm run test:e2e
```

Copy `e2e/.env.example` to `e2e/.env` to change the site URL or login.

Copies are rendered by a request from the site to its own page (`Loopback_Content_Renderer`). Inside the wp-env containers the site's address, `localhost` and its port, does not reach the web server, so `e2e/mu-plugins/llms-e2e-loopback.php` sends that request to the compose service (`wordpress` or `tests-wordpress`) with the site's Host header.

`seo-noindex.spec.js` runs the same tests against Yoast SEO, Rank Math, SEOPress, AIOSEO and Slim SEO, one at a time. The global setup installs them from wordpress.org, inactive. `e2e/mu-plugins/llms-e2e-seo.php` adds `wp llms-e2e-seo`, which sets noindex where each plugin stores it, and each test checks the copy against the robots tag the plugin prints.

`membership.spec.js` does the same for Paid Memberships Pro, Members, Content Control, Ultimate Member, Restrict Content, LifterLMS and WooCommerce Memberships, through `wp llms-e2e-access`, checking the copy against whether a logged out visitor sees the post's content. Paid Memberships Pro is installed from its GitHub tag, as it has left wordpress.org. WooCommerce Memberships is paid, so it is mapped in from a local copy by `.wp-env.override.json`, which is not committed:

```json
{
	"mappings": {
		"wp-content/mu-plugins": "./e2e/mu-plugins",
		"wp-content/plugins/woocommerce-memberships": "/path/to/woocommerce-memberships"
	}
}
```

`cache.spec.js` turns on the page cache of WP Super Cache, W3 Total Cache and WP Fastest Cache, and Autoptimize, one at a time, through each plugin's own commands and settings (`e2e/fixtures/cache-plugins.js`). Each first checks the plugin is serving a page from its cache, then that `llms.txt` and a copy show each change, redirected and sent by PHP. WP Rocket is paid, so `e2e/mu-plugins/llms-e2e-cache.php` stands in for its `rocket_clean_files()` and records the URLs it is given. LiteSpeed Cache needs a LiteSpeed web server, so only its `litespeed_purge_url` action is covered, by PHPUnit.

`sitemap.spec.js` turns on Yoast SEO and Rank Math, one at a time, and checks the sitemap index lists `llms-sitemap.xml`, with llms.txt in it, only while "Let search engines index llms.txt" is on, and that each plugin's cached sitemaps change when the setting changes or llms.txt is rebuilt.

### GitHub workflows

* `tests.yml` PHPUnit on PHP 8.2 to 8.4.
* `quality.yml` PHPCS and PHPStan.
* `e2e.yml` Playwright, on pull requests.
* `mutation.yml` Infection, one thread, started by hand.
* `release.yml` builds `llms-helper.zip` when a GitHub release is published.

## Layout

* `src/Container` small autowiring DI container.
* `src/Hooks` the `Hook_Loader` and `Hookable` interface.
* `src/App.php` builds each controller through the container and registers its hooks.
* `src/Plugin.php` the DI rules binding each interface to its class.
* `src/Interfaces` the interfaces each part is swapped through.
* `src/Controllers` the hooked classes.
* `src/Content` the converter, content renderers, file store, copy and llms.txt builders, and the SEO and membership plugin checks.
* `src/Sitemap` the llms sitemap and its Yoast SEO and Rank Math providers.
* `src/Http` routes `.md` and `/llms.txt` requests before the rest of WordPress loads.
* `src/Queue` and `src/Cron` background jobs, through Action Scheduler when installed, otherwise WP-Cron.
* `src/Settings`, `src/Cache`, `src/CLI` the settings option, cache purging and WP-CLI commands.
