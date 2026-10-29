<?php
/**
 * E2E helper for core.spec.js: records the URLs given to llms_helper_purge_urls while llms_e2e_record_purges is set,
 * and kills the process while a copy is written while llms_e2e_crash_build is set, as a fatal error would.
 * `wp llms-e2e-core reset` puts the settings, queue and these options back in one call.
 *
 * @package PinkCrab\LLMs_Helper\Tests
 */

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	WP_CLI::add_command(
		'llms-e2e-core reset',
		static function (): void {
			delete_option( 'llms_helper_settings' );
			foreach ( array( 'llms_helper_build_batch', 'llms_helper_build_index', 'llms_helper_build_posts', 'llms_helper_build_retry' ) as $hook ) {
				wp_unschedule_hook( $hook );
			}
			foreach ( array( 'llms_e2e_record_purges', 'llms_e2e_purged', 'llms_e2e_crash_build', 'llms_helper_build_failed', 'llms_e2e_break_loopback' ) as $option ) {
				delete_option( $option );
			}
		}
	);
}

add_filter(
	'llms_helper_front_matter',
	static function ( array $data ): array {
		if ( get_option( 'llms_e2e_crash_build' ) ) {
			exit( 1 );
		}
		return $data;
	}
);

add_action(
	'llms_helper_purge_urls',
	static function ( array $urls ): void {
		if ( get_option( 'llms_e2e_record_purges' ) ) {
			update_option( 'llms_e2e_purged', array_merge( (array) get_option( 'llms_e2e_purged', array() ), $urls ), false );
		}
	}
);
