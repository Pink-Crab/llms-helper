<?php
/**
 * Tests for Plugin.
 *
 * @package PinkCrab\LLMs_Helper\Tests
 */

declare(strict_types=1);

namespace PinkCrab\LLMs_Helper\Tests;

use WP_UnitTestCase;
use PinkCrab\LLMs_Helper\Plugin;
use PinkCrab\LLMs_Helper\Hooks\Hookable;
use PinkCrab\LLMs_Helper\Queue\Auto_Queue;
use PinkCrab\LLMs_Helper\Cache\Cache_Plugin_Purger;
use PinkCrab\LLMs_Helper\Interfaces\Queue;
use PinkCrab\LLMs_Helper\Container\Container;
use PinkCrab\LLMs_Helper\Interfaces\Url_Map;
use PinkCrab\LLMs_Helper\Interfaces\Settings;
use PinkCrab\LLMs_Helper\Content\Uploads_Store;
use PinkCrab\LLMs_Helper\Content\Html_Converter;
use PinkCrab\LLMs_Helper\Interfaces\Copy_Store;
use PinkCrab\LLMs_Helper\Content\Llmstxt_Url_Map;
use PinkCrab\LLMs_Helper\Controllers\Post_Events;
use PinkCrab\LLMs_Helper\Interfaces\Cache_Purger;
use PinkCrab\LLMs_Helper\Settings\Option_Settings;
use PinkCrab\LLMs_Helper\Content\Markdown_Formatter;
use PinkCrab\LLMs_Helper\Interfaces\Copy_Formatter;
use PinkCrab\LLMs_Helper\Interfaces\Index_Generator;
use PinkCrab\LLMs_Helper\Interfaces\Content_Renderer;
use PinkCrab\LLMs_Helper\Interfaces\Inclusion_Policy;
use PinkCrab\LLMs_Helper\Content\Copy_Index_Generator;
use PinkCrab\LLMs_Helper\Content\Loopback_Content_Renderer;
use PinkCrab\LLMs_Helper\Interfaces\Markdown_Converter;
use PinkCrab\LLMs_Helper\Tests\Fakes\Recording_Purger;
use PinkCrab\LLMs_Helper\Content\Default_Inclusion_Policy;
use PinkCrab\LLMs_Helper\Interfaces\Noindex_Check;
use PinkCrab\LLMs_Helper\Content\Seo_Plugin_Noindex;
use PinkCrab\LLMs_Helper\Interfaces\Access_Check;
use PinkCrab\LLMs_Helper\Content\Membership_Plugin_Access;
use PinkCrab\LLMs_Helper\Interfaces\Description_Source;
use PinkCrab\LLMs_Helper\Content\Seo_Plugin_Description;

class Test_Plugin extends WP_UnitTestCase {

	/**
	 * @return array<string, array{class-string, class-string}>
	 */
	public function binding_provider(): array {
		return array(
			'settings'  => array( Settings::class, Option_Settings::class ),
			'queue'     => array( Queue::class, Auto_Queue::class ),
			'store'     => array( Copy_Store::class, Uploads_Store::class ),
			'urls'      => array( Url_Map::class, Llmstxt_Url_Map::class ),
			'converter' => array( Markdown_Converter::class, Html_Converter::class ),
			'renderer'  => array( Content_Renderer::class, Loopback_Content_Renderer::class ),
			'formatter' => array( Copy_Formatter::class, Markdown_Formatter::class ),
			'policy'    => array( Inclusion_Policy::class, Default_Inclusion_Policy::class ),
			'noindex'   => array( Noindex_Check::class, Seo_Plugin_Noindex::class ),
			'access'    => array( Access_Check::class, Membership_Plugin_Access::class ),
			'describe'  => array( Description_Source::class, Seo_Plugin_Description::class ),
			'generator' => array( Index_Generator::class, Copy_Index_Generator::class ),
			'purger'    => array( Cache_Purger::class, Cache_Plugin_Purger::class ),
		);
	}

	/**
	 * @dataProvider binding_provider
	 */
	public function test_each_interface_resolves_to_its_default( string $contract, string $implementation ): void {
		$this->assertInstanceOf( $implementation, ( new Container( Plugin::di_rules() ) )->get( $contract ) );
	}

	public function test_services_are_shared(): void {
		$container = new Container( Plugin::di_rules() );

		$this->assertSame( $container->get( Settings::class ), $container->get( Option_Settings::class ) );
		$this->assertSame( $container->get( Copy_Store::class ), $container->get( Copy_Store::class ) );
	}

	public function test_every_controller_is_hookable_and_builds(): void {
		$container = new Container( Plugin::di_rules() );

		foreach ( Plugin::CONTROLLERS as $controller ) {
			$this->assertInstanceOf( Hookable::class, $container->get( $controller ) );
		}
	}

	public function test_the_booted_plugin_registered_its_hooks(): void {
		global $wp_filter;

		$found = false;
		foreach ( $wp_filter['wp_after_insert_post']->callbacks as $callbacks ) {
			foreach ( $callbacks as $callback ) {
				$found = $found || ( is_array( $callback['function'] ) && $callback['function'][0] instanceof Post_Events );
			}
		}

		$this->assertTrue( $found );
	}

	public function test_di_rules_filter_swaps_an_implementation(): void {
		add_filter(
			'llms_helper_di_rules',
			static function ( array $rules ): array {
				$rules[ Cache_Purger::class ] = array( 'instance_of' => Recording_Purger::class );
				return $rules;
			}
		);

		$container = Plugin::boot( dirname( __DIR__ ) . '/llms-helper.php' );

		$this->assertInstanceOf( Recording_Purger::class, $container->get( Cache_Purger::class ) );
	}
}
