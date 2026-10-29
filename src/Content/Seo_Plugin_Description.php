<?php
/**
 * Meta descriptions from the active SEO plugins.
 *
 * @author Glynn Quelch <glynn.quelch@gmail.com>
 * @license https://www.gnu.org/licenses/gpl-2.0.txt GPL-2.0-or-later
 * @package PinkCrab\LLMs_Helper
 * @since 0.1.0
 */

declare(strict_types=1);

namespace PinkCrab\LLMs_Helper\Content;

use WP_Post;
use PinkCrab\LLMs_Helper\Interfaces\Description_Source;

/**
 * The meta description the first active SEO plugin prints for a post: Yoast, Rank Math, SEOPress, AIOSEO or
 * Slim SEO, each through its own per post function, so templates and variables come out as on the page.
 * The plugins' functions are only called once active() has found them.
 */
class Seo_Plugin_Description implements Description_Source {

	/**
	 * Plugins checked, in order, by key.
	 *
	 * @var list<string>
	 */
	public const PLUGINS = array( 'yoast', 'rank_math', 'seopress', 'aioseo', 'slim_seo' );

	/**
	 * {@inheritDoc}
	 */
	public function post( WP_Post $post ): string {
		foreach ( self::PLUGINS as $plugin ) {
			$description = $this->active( $plugin ) ? Text::plain( $this->post_description( $plugin, $post ) ) : '';
			if ( '' !== $description ) {
				return $description;
			}
		}

		return '';
	}

	/**
	 * {@inheritDoc}
	 */
	public function site(): string {
		if ( $this->active( 'yoast' ) ) {
			$meta = YoastSEO()->meta->for_home_page();
			if ( $meta ) {
				return Text::plain( (string) $meta->description );
			}
		}

		if ( $this->active( 'rank_math' ) && ! \RankMath\Helper::is_invalid_registration() ) {
			rank_math()->variables->setup();
			return Text::plain( $this->rank_math_output( ( new \RankMath\Paper\Blog() )->description() ) );
		}

		return '';
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
				return function_exists( 'YoastSEO' );
			case 'rank_math':
				return class_exists( '\RankMath\Paper\Singular' );
			case 'seopress':
				return function_exists( 'seopress_get_service' ) && function_exists( 'seopress_get_toggle_option' );
			case 'aioseo':
				return function_exists( 'aioseo' );
			case 'slim_seo':
				return class_exists( '\SlimSEO\MetaTags\Description' );
			default:
				return false;
		}
	}

	/**
	 * One plugin's description for the post.
	 *
	 * @param string  $plugin Plugin key.
	 * @param WP_Post $post   Post.
	 * @return string
	 */
	protected function post_description( string $plugin, WP_Post $post ): string {
		switch ( $plugin ) {
			case 'yoast':
				$meta = YoastSEO()->meta->for_post( $post->ID );
				return $meta ? (string) $meta->description : '';
			case 'rank_math':
				return $this->rank_math( $post );
			case 'seopress':
				return $this->seopress( $post );
			case 'aioseo':
				return (string) aioseo()->meta->description->getPostDescription( $post );
			case 'slim_seo':
				return $this->slim_seo( $post );
			default:
				return '';
		}
	}

	/**
	 * Rank Math: its post paper, which prints nothing until its setup is registered or skipped.
	 *
	 * @param WP_Post $post Post.
	 * @return string
	 */
	protected function rank_math( WP_Post $post ): string {
		if ( \RankMath\Helper::is_invalid_registration() ) {
			return '';
		}

		// Its template variables are only set up on its own request hooks, which builds from cron or WP-CLI miss.
		rank_math()->variables->setup();

		$paper = new \RankMath\Paper\Singular();
		$paper->set_object( $post );

		return $this->rank_math_output( $paper->description() );
	}

	/**
	 * What Rank Math's Paper::get_description() does before printing a description.
	 *
	 * @param mixed $description Paper description.
	 * @return string
	 */
	protected function rank_math_output( $description ): string {
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound, WordPress.NamingConventions.ValidHookName.UseUnderscores -- Rank Math's hook.
		$description = (string) apply_filters( 'rank_math/frontend/description', trim( (string) $description ) );

		return wp_strip_all_tags( stripslashes( $description ), true );
	}

	/**
	 * SEOPress: with its Titles module on, the post's description or its post type template, with tags replaced,
	 * as its admin description column does.
	 *
	 * @param WP_Post $post Post.
	 * @return string
	 */
	protected function seopress( WP_Post $post ): string {
		if ( '1' !== (string) seopress_get_toggle_option( 'titles' ) ) {
			return '';
		}

		$value = (string) get_post_meta( $post->ID, '_seopress_titles_desc', true );
		if ( '' === $value ) {
			$value = (string) seopress_get_service( 'TitleOption' )->getSingleCptDesc( $post->ID );
		}

		$context = seopress_get_service( 'ContextPage' )->buildContextWithCurrentId( $post->ID )->getContext();

		return (string) seopress_get_service( 'TagsToString' )->replace( $value, $context );
	}

	/**
	 * Slim SEO: with its meta description feature on, its rendered description for the post.
	 *
	 * @param WP_Post $post Post.
	 * @return string
	 */
	protected function slim_seo( WP_Post $post ): string {
		$options  = get_option( 'slim_seo' );
		$features = is_array( $options ) && ! empty( $options['features'] ) ? (array) $options['features'] : array();

		// No saved features means Slim SEO's defaults, which include meta descriptions.
		if ( array() !== $features && ! in_array( 'meta_description', $features, true ) ) {
			return '';
		}

		return ( new \SlimSEO\MetaTags\Description() )->get_rendered_singular_value( $post->ID );
	}
}
