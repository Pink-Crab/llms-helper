<?php
/**
 * E2E helper for core.spec.js: records the URLs given to llms_helper_purge_urls while llms_e2e_record_purges is set,
 * and kills the process while a copy is written while llms_e2e_crash_build is set, as a fatal error would.
 *
 * @package PinkCrab\LLMs_Helper\Tests
 */

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
