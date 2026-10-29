<?php
/**
 * Wires the plugin together.
 *
 * @author Glynn Quelch <glynn.quelch@gmail.com>
 * @license https://www.gnu.org/licenses/gpl-2.0.txt GPL-2.0-or-later
 * @package PinkCrab\LLMs_Helper
 * @since 0.1.0
 */

declare(strict_types=1);

namespace PinkCrab\LLMs_Helper;

use PinkCrab\LLMs_Helper\CLI\Command;
use PinkCrab\LLMs_Helper\Queue\Auto_Queue;
use PinkCrab\LLMs_Helper\Http\Responder;
use PinkCrab\LLMs_Helper\Cache\Cache_Plugin_Purger;
use PinkCrab\LLMs_Helper\Interfaces\Queue;
use PinkCrab\LLMs_Helper\Container\Container;
use PinkCrab\LLMs_Helper\Http\Request_Router;
use PinkCrab\LLMs_Helper\Interfaces\Url_Map;
use PinkCrab\LLMs_Helper\Interfaces\Settings;
use PinkCrab\LLMs_Helper\Controllers\Meta_Box;
use PinkCrab\LLMs_Helper\Content\Uploads_Store;
use PinkCrab\LLMs_Helper\Content\Html_Converter;
use PinkCrab\LLMs_Helper\Interfaces\Copy_Store;
use PinkCrab\LLMs_Helper\Content\Llmstxt_Url_Map;
use PinkCrab\LLMs_Helper\Controllers\Cron_Events;
use PinkCrab\LLMs_Helper\Controllers\Post_Events;
use PinkCrab\LLMs_Helper\Interfaces\Cache_Purger;
use PinkCrab\LLMs_Helper\Settings\Option_Settings;
use PinkCrab\LLMs_Helper\Content\Markdown_Formatter;
use PinkCrab\LLMs_Helper\Controllers\Settings_Page;
use PinkCrab\LLMs_Helper\Interfaces\Copy_Formatter;
use PinkCrab\LLMs_Helper\Controllers\Discovery_Link;
use PinkCrab\LLMs_Helper\Controllers\WooCommerce_Events;
use PinkCrab\LLMs_Helper\Controllers\Product_Front_Matter;
use PinkCrab\LLMs_Helper\Controllers\Term_Events;
use PinkCrab\LLMs_Helper\Interfaces\Index_Generator;
use PinkCrab\LLMs_Helper\Interfaces\Content_Renderer;
use PinkCrab\LLMs_Helper\Interfaces\Inclusion_Policy;
use PinkCrab\LLMs_Helper\Content\Copy_Index_Generator;
use PinkCrab\LLMs_Helper\Content\Loopback_Content_Renderer;
use PinkCrab\LLMs_Helper\Controllers\Render_Endpoint;
use PinkCrab\LLMs_Helper\Controllers\Sitemap_Events;
use PinkCrab\LLMs_Helper\Interfaces\Markdown_Converter;
use PinkCrab\LLMs_Helper\Content\Default_Inclusion_Policy;
use PinkCrab\LLMs_Helper\Interfaces\Noindex_Check;
use PinkCrab\LLMs_Helper\Content\Seo_Plugin_Noindex;
use PinkCrab\LLMs_Helper\Interfaces\Access_Check;
use PinkCrab\LLMs_Helper\Content\Membership_Plugin_Access;
use PinkCrab\LLMs_Helper\Interfaces\Description_Source;
use PinkCrab\LLMs_Helper\Content\Seo_Plugin_Description;

/**
 * Builds the container, answers copy and llms.txt requests early, and boots the controllers.
 */
class Plugin {

	/**
	 * Controllers built through the container and registered on boot.
	 *
	 * @var array<class-string>
	 */
	public const CONTROLLERS = array(
		Post_Events::class,
		Cron_Events::class,
		Settings_Page::class,
		Meta_Box::class,
		Discovery_Link::class,
		WooCommerce_Events::class,
		Product_Front_Matter::class,
		Term_Events::class,
		Render_Endpoint::class,
		Sitemap_Events::class,
	);

	/**
	 * Default container rules: every class shared, every interface bound to its default implementation.
	 *
	 * @return array<string, array{shared?: bool, instance_of?: class-string, construct_params?: array<string, mixed>}>
	 */
	public static function di_rules(): array {
		return array(
			'*'                       => array( 'shared' => true ),
			Settings::class           => array( 'instance_of' => Option_Settings::class ),
			Queue::class              => array( 'instance_of' => Auto_Queue::class ),
			Copy_Store::class         => array( 'instance_of' => Uploads_Store::class ),
			Url_Map::class            => array( 'instance_of' => Llmstxt_Url_Map::class ),
			Markdown_Converter::class => array( 'instance_of' => Html_Converter::class ),
			Content_Renderer::class   => array( 'instance_of' => Loopback_Content_Renderer::class ),
			Copy_Formatter::class     => array( 'instance_of' => Markdown_Formatter::class ),
			Inclusion_Policy::class   => array( 'instance_of' => Default_Inclusion_Policy::class ),
			Noindex_Check::class      => array( 'instance_of' => Seo_Plugin_Noindex::class ),
			Access_Check::class       => array( 'instance_of' => Membership_Plugin_Access::class ),
			Description_Source::class => array( 'instance_of' => Seo_Plugin_Description::class ),
			Index_Generator::class    => array( 'instance_of' => Copy_Index_Generator::class ),
			Cache_Purger::class       => array( 'instance_of' => Cache_Plugin_Purger::class ),
		);
	}

	/**
	 * Boot the plugin.
	 *
	 * @param string $file Main plugin file.
	 * @return Container
	 */
	public static function boot( string $file ): Container {
		/**
		 * Filter the container rules, to swap an interface's implementation.
		 * Runs when this plugin loads, so hook it from an mu-plugin or a plugin that loads earlier.
		 *
		 * @param array<string, array<string, mixed>> $rules Rules by class or interface name.
		 */
		$rules     = (array) apply_filters( 'llms_helper_di_rules', self::di_rules() );
		$container = new Container( $rules );

		// Answer copy and llms.txt requests now, before the remaining plugins and the theme load.
		if ( self::is_front_request() ) {
			$container->get( Responder::class )->respond( $container->get( Request_Router::class )->route( $_SERVER ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		}

		$container->get( App::class )->boot( self::CONTROLLERS );

		$lifecycle = $container->get( Lifecycle::class );
		register_activation_hook( $file, array( $lifecycle, 'activate' ) );
		register_deactivation_hook( $file, array( $lifecycle, 'deactivate' ) );

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command( 'llms-helper', $container->get( Command::class ) );
		}

		return $container;
	}

	/**
	 * Whether this is a front end request that could be for a copy or llms.txt.
	 *
	 * @return bool
	 */
	private static function is_front_request(): bool {
		return ! is_admin() && ! wp_doing_cron() && ! wp_doing_ajax() && ! ( defined( 'WP_CLI' ) && WP_CLI );
	}
}
