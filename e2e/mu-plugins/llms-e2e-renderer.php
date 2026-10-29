<?php
/**
 * E2E helper for renderer.spec.js: swaps in the feed renderer through llms_helper_di_rules while the
 * llms_e2e_feed_renderer option is set, and marks feed content so the spec can tell which renderer ran.
 *
 * @package PinkCrab\LLMs_Helper\Tests
 */

add_filter(
	'llms_helper_di_rules',
	static function ( array $rules ): array {
		if ( get_option( 'llms_e2e_feed_renderer' ) ) {
			$rules['PinkCrab\LLMs_Helper\Interfaces\Content_Renderer'] = array( 'instance_of' => 'PinkCrab\LLMs_Helper\Content\Feed_Content_Renderer' );
		}

		return $rules;
	}
);

add_filter(
	'the_content_feed',
	static function ( string $content ): string {
		return $content . '<p>FEED-ONLY-TEXT</p>';
	}
);
