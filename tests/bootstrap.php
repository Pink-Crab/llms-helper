<?php
/**
 * PHPUnit bootstrap.
 *
 * @package PinkCrab\LLMs_Helper\Tests
 */

declare(strict_types=1);

// Composer autoloader must be loaded before WP_PHPUNIT__DIR is available.
require_once dirname( __DIR__ ) . '/vendor/autoload.php';

// Gives access to tests_add_filter().
require_once getenv( 'WP_PHPUNIT__DIR' ) . '/includes/functions.php';

// Database details from tests/.env; CI sets them in tests/wp-config.php instead.
if ( file_exists( __DIR__ . '/.env' ) ) {
	Dotenv\Dotenv::createUnsafeImmutable( __DIR__ )->load();
}

tests_add_filter(
	'muplugins_loaded',
	static function (): void {
		require dirname( __DIR__ ) . '/llms-helper.php';
	}
);

// No web server runs the suite, so copies render in this request unless a test answers the request itself.
tests_add_filter(
	'pre_http_request',
	static function ( $pre, array $args, string $url ) {
		return false === $pre && str_contains( $url, 'llms_helper_render=' ) ? new WP_Error( 'llms_helper_tests', 'No web server in the test suite.' ) : $pre;
	},
	99,
	3
);

require getenv( 'WP_PHPUNIT__DIR' ) . '/includes/bootstrap.php';
