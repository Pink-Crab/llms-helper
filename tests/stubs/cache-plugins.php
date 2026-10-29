<?php
/**
 * PHPStan declarations for the cache plugin purges Cache_Plugin_Purger calls. Never loaded.
 *
 * @package PinkCrab\LLMs_Helper\Tests
 */

/**
 * WP Rocket 3.21.0.1, inc/functions/files.php.
 *
 * @param string|array<int, string> $urls        URLs.
 * @param mixed                     $filesystem  Filesystem, WP Rocket's own when null.
 * @param bool                      $run_actions Fire WP Rocket's purge actions.
 * @return void
 */
function rocket_clean_files( $urls, $filesystem = null, $run_actions = true ) {}

/**
 * W3 Total Cache 2.11.0, w3-total-cache-api.php.
 *
 * @param string     $w3tc_url URL.
 * @param mixed|null $extras   Extra options.
 * @return void
 */
function w3tc_flush_url( $w3tc_url, $extras = null ) {}

/**
 * WP Super Cache 3.1.4, wp-cache-phase2.php.
 *
 * @param string $url URL.
 * @return bool
 */
function wpsc_delete_url_cache( $url ) {}

/**
 * WP Fastest Cache 1.5.3, wpFastestCache.php.
 *
 * @param string|false $url URL.
 * @return void
 */
function wpfc_clear_cache_by_url( $url = false ) {}
