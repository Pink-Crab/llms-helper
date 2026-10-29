<?php
/**
 * Noindex settings of the active SEO plugins.
 *
 * @author Glynn Quelch <glynn.quelch@gmail.com>
 * @license https://www.gnu.org/licenses/gpl-2.0.txt GPL-2.0-or-later
 * @package PinkCrab\LLMs_Helper
 * @since 0.1.0
 */

declare(strict_types=1);

namespace PinkCrab\LLMs_Helper\Content;

use WP_Post;
use ReflectionClass;
use PinkCrab\LLMs_Helper\Interfaces\Noindex_Check;
use AIOSEO\Plugin\Common\Meta\Robots as Aioseo_Robots;

/**
 * A post is noindex when any active SEO plugin prints noindex for it: Yoast, Rank Math, SEOPress, AIOSEO or Slim SEO.
 * Each follows that plugin's own rule, the post's setting first, then its post type default, and only while
 * the plugin is printing its robots tag. The plugins' functions are only called once active() has found them.
 */
class Seo_Plugin_Noindex implements Noindex_Check {

	/**
	 * Plugins checked, by key.
	 *
	 * @var list<string>
	 */
	public const PLUGINS = array( 'yoast', 'rank_math', 'seopress', 'aioseo', 'slim_seo' );

	/**
	 * {@inheritDoc}
	 */
	public function noindex( WP_Post $post ): bool {
		foreach ( self::PLUGINS as $plugin ) {
			if ( $this->active( $plugin ) && $this->check( $plugin, $post ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether an SEO plugin is active.
	 *
	 * @param string $plugin Plugin key.
	 * @return bool
	 */
	protected function active( string $plugin ): bool {
		switch ( $plugin ) {
			case 'yoast':
				return defined( 'WPSEO_VERSION' );
			case 'rank_math':
				return is_callable( array( '\RankMath\Helper', 'is_post_indexable' ) );
			case 'seopress':
				return function_exists( 'seopress_get_service' ) && function_exists( 'seopress_get_toggle_option' );
			case 'aioseo':
				return function_exists( 'aioseo' ) && class_exists( Aioseo_Robots::class );
			case 'slim_seo':
				return defined( 'SLIM_SEO_VER' );
			default:
				return false;
		}
	}

	/**
	 * Run one plugin's check.
	 *
	 * @param string  $plugin Plugin key.
	 * @param WP_Post $post   Post.
	 * @return bool
	 */
	protected function check( string $plugin, WP_Post $post ): bool {
		switch ( $plugin ) {
			case 'yoast':
				return $this->yoast( $post );
			case 'rank_math':
				return $this->rank_math( $post );
			case 'seopress':
				return $this->seopress( $post );
			case 'aioseo':
				return $this->aioseo( $post );
			case 'slim_seo':
				return $this->slim_seo( $post );
			default:
				return false;
		}
	}

	/**
	 * Yoast: the post's setting ('1' noindex, '2' index), else the post type's noindex or disable option.
	 *
	 * @param WP_Post $post Post.
	 * @return bool
	 */
	protected function yoast( WP_Post $post ): bool {
		$setting = (string) get_post_meta( $post->ID, '_yoast_wpseo_meta-robots-noindex', true );
		if ( '1' === $setting || '2' === $setting ) {
			return '1' === $setting;
		}

		$titles = get_option( 'wpseo_titles' );

		return is_array( $titles )
			&& ( ! empty( $titles[ 'noindex-' . $post->post_type ] ) || ! empty( $titles[ 'disable-' . $post->post_type ] ) );
	}

	/**
	 * Rank Math: its own indexable check, without its sitemap exclusions. It prints nothing until its setup is registered or skipped.
	 *
	 * @param WP_Post $post Post.
	 * @return bool
	 */
	protected function rank_math( WP_Post $post ): bool {
		return ! \RankMath\Helper::is_invalid_registration()
			&& ! \RankMath\Helper::is_post_indexable( $post->ID, false );
	}

	/**
	 * SEOPress: with its Titles module on, the site wide noindex, the post type's noindex, or the post's setting.
	 *
	 * @param WP_Post $post Post.
	 * @return bool
	 */
	protected function seopress( WP_Post $post ): bool {
		if ( '1' !== (string) seopress_get_toggle_option( 'titles' ) ) {
			return false;
		}

		$titles = seopress_get_service( 'TitleOption' );

		return ( null !== $titles && ( $titles->getTitleNoIndex() || $titles->getSingleCptNoIndex( $post->ID ) ) )
			|| 'yes' === get_post_meta( $post->ID, '_seopress_robots_index', true );
	}

	/**
	 * AIOSEO: its own robots attributes for the post.
	 *
	 * @param WP_Post $post Post.
	 * @return bool
	 */
	protected function aioseo( WP_Post $post ): bool {
		// A fresh instance: the constructor only adds hooks, and AIOSEO's shared one keeps attributes between calls.
		$robots = ( new ReflectionClass( Aioseo_Robots::class ) )->newInstanceWithoutConstructor();
		$robots->post( $post );
		$attributes = $robots->metaHelper( true );

		return is_array( $attributes ) && ! empty( $attributes['noindex'] );
	}

	/**
	 * Slim SEO: with its meta robots feature on, the post's setting or the post type's noindex option.
	 *
	 * @param WP_Post $post Post.
	 * @return bool
	 */
	protected function slim_seo( WP_Post $post ): bool {
		$options  = get_option( 'slim_seo' );
		$options  = is_array( $options ) ? $options : array();
		$features = empty( $options['features'] ) ? array() : (array) $options['features'];

		// No saved features means Slim SEO's defaults, which include meta robots.
		if ( array() !== $features && ! in_array( 'meta_robots', $features, true ) ) {
			return false;
		}

		$data = get_post_meta( $post->ID, 'slim_seo', true );

		return ( is_array( $data ) && ! empty( $data['noindex'] ) )
			|| ! empty( $options[ $post->post_type ]['noindex'] );
	}
}
