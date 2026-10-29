<?php
/**
 * WP-CLI helper for seo-noindex.spec.js: sets noindex where each SEO plugin stores it.
 *
 * Usage:
 *   wp llms-e2e-seo setup <plugin>
 *   wp llms-e2e-seo post <plugin> <post-id> <noindex|index>
 *   wp llms-e2e-seo post-type <plugin> <post-type> <noindex|reset>
 *   wp llms-e2e-seo description <plugin> <post-id> <text>
 *   wp llms-e2e-seo site-description <plugin> [<text>]
 *
 * @package PinkCrab\LLMs_Helper\Tests
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

/**
 * Turn on the plugin's robots output, as finishing its setup would.
 *
 * @param string $plugin Plugin slug.
 */
function llms_e2e_seo_setup( string $plugin ): void {
	switch ( $plugin ) {
		case 'seo-by-rank-math':
			update_option( 'rank_math_registration_skip', 1 );
			break;
		case 'wp-seopress':
			$toggle                  = (array) get_option( 'seopress_toggle', array() );
			$toggle['toggle-titles'] = '1';
			update_option( 'seopress_toggle', $toggle );
			break;
	}
}

/**
 * Set one post to noindex or index.
 *
 * @param string $plugin  Plugin slug.
 * @param int    $post_id Post ID.
 * @param bool   $noindex Noindex or index.
 */
function llms_e2e_seo_post( string $plugin, int $post_id, bool $noindex ): void {
	switch ( $plugin ) {
		case 'wordpress-seo':
			update_post_meta( $post_id, '_yoast_wpseo_meta-robots-noindex', $noindex ? '1' : '2' );
			break;
		case 'seo-by-rank-math':
			update_post_meta( $post_id, 'rank_math_robots', array( $noindex ? 'noindex' : 'index' ) );
			break;
		case 'wp-seopress':
			update_post_meta( $post_id, '_seopress_robots_index', $noindex ? 'yes' : '' );
			break;
		case 'all-in-one-seo-pack':
			$post                 = \AIOSEO\Plugin\Common\Models\Post::getPost( $post_id );
			$post->robots_default = false;
			$post->robots_noindex = $noindex;
			$post->save();
			break;
		case 'slim-seo':
			update_post_meta( $post_id, 'slim_seo', array( 'noindex' => $noindex ? 1 : 0 ) );
			break;
	}
}

/**
 * Set one post's own meta description.
 *
 * @param string $plugin      Plugin slug.
 * @param int    $post_id     Post ID.
 * @param string $description Description.
 */
function llms_e2e_seo_description( string $plugin, int $post_id, string $description ): void {
	switch ( $plugin ) {
		case 'wordpress-seo':
			update_post_meta( $post_id, '_yoast_wpseo_metadesc', $description );
			break;
		case 'seo-by-rank-math':
			update_post_meta( $post_id, 'rank_math_description', $description );
			break;
		case 'wp-seopress':
			update_post_meta( $post_id, '_seopress_titles_desc', $description );
			break;
		case 'all-in-one-seo-pack':
			$post              = \AIOSEO\Plugin\Common\Models\Post::getPost( $post_id );
			$post->description = $description;
			$post->save();
			break;
		case 'slim-seo':
			$data                = (array) get_post_meta( $post_id, 'slim_seo', true );
			$data['description'] = $description;
			update_post_meta( $post_id, 'slim_seo', $data );
			break;
	}
}

/**
 * Set the home page meta description, for plugins that have one.
 *
 * @param string $plugin      Plugin slug.
 * @param string $description Description, empty to clear it.
 */
function llms_e2e_seo_site_description( string $plugin, string $description ): void {
	switch ( $plugin ) {
		case 'wordpress-seo':
			$titles                        = (array) get_option( 'wpseo_titles', array() );
			$titles['metadesc-home-wpseo'] = $description;
			update_option( 'wpseo_titles', $titles );
			break;
		case 'seo-by-rank-math':
			$titles                         = (array) get_option( 'rank-math-options-titles', array() );
			$titles['homepage_description'] = $description;
			update_option( 'rank-math-options-titles', $titles );
			break;
	}
}

/**
 * Set a post type to noindex, or back to the plugin's default.
 *
 * @param string $plugin    Plugin slug.
 * @param string $post_type Post type.
 * @param bool   $noindex   Noindex or reset.
 */
function llms_e2e_seo_post_type( string $plugin, string $post_type, bool $noindex ): void {
	switch ( $plugin ) {
		case 'wordpress-seo':
			$titles                            = (array) get_option( 'wpseo_titles', array() );
			$titles[ 'noindex-' . $post_type ] = $noindex;
			update_option( 'wpseo_titles', $titles );
			break;
		case 'seo-by-rank-math':
			$titles                                    = (array) get_option( 'rank-math-options-titles', array() );
			$titles[ "pt_{$post_type}_custom_robots" ] = $noindex ? 'on' : 'off';
			$titles[ "pt_{$post_type}_robots" ]        = $noindex ? array( 'noindex' ) : array();
			update_option( 'rank-math-options-titles', $titles );
			break;
		case 'wp-seopress':
			$titles = (array) get_option( 'seopress_titles_option_name', array() );
			if ( $noindex ) {
				$titles['seopress_titles_single_titles'][ $post_type ]['noindex'] = '1';
			} else {
				unset( $titles['seopress_titles_single_titles'][ $post_type ]['noindex'] );
			}
			update_option( 'seopress_titles_option_name', $titles );
			break;
		case 'all-in-one-seo-pack':
			aioseo()->dynamicOptions->searchAppearance->postTypes->$post_type->show = ! $noindex;
			aioseo()->dynamicOptions->save( true );
			break;
		case 'slim-seo':
			$options                          = (array) get_option( 'slim_seo', array() );
			$options[ $post_type ]['noindex'] = $noindex ? 1 : 0;
			update_option( 'slim_seo', $options );
			break;
	}
}

WP_CLI::add_command(
	'llms-e2e-seo',
	static function ( array $args ): void {
		$action = $args[0] ?? '';
		$plugin = $args[1] ?? '';

		switch ( $action ) {
			case 'setup':
				llms_e2e_seo_setup( $plugin );
				break;
			case 'post':
				llms_e2e_seo_post( $plugin, (int) ( $args[2] ?? 0 ), 'noindex' === ( $args[3] ?? '' ) );
				break;
			case 'post-type':
				llms_e2e_seo_post_type( $plugin, (string) ( $args[2] ?? 'post' ), 'noindex' === ( $args[3] ?? '' ) );
				break;
			case 'description':
				llms_e2e_seo_description( $plugin, (int) ( $args[2] ?? 0 ), (string) ( $args[3] ?? '' ) );
				break;
			case 'site-description':
				llms_e2e_seo_site_description( $plugin, (string) ( $args[2] ?? '' ) );
				break;
			default:
				WP_CLI::error( 'Use setup, post, post-type, description or site-description.' );
		}

		WP_CLI::success( "{$action} {$plugin} done." );
	}
);
