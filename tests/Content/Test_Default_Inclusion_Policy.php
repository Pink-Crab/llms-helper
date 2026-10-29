<?php
/**
 * Tests for Default_Inclusion_Policy.
 *
 * @package PinkCrab\LLMs_Helper\Tests
 */

declare(strict_types=1);

namespace PinkCrab\LLMs_Helper\Tests\Content;

use WP_Post;
use WP_UnitTestCase;
use PinkCrab\LLMs_Helper\Tests\Fakes\Fake_Settings;
use PinkCrab\LLMs_Helper\Tests\Fakes\Fixed_Access;
use PinkCrab\LLMs_Helper\Tests\Fakes\Fixed_Noindex;
use PinkCrab\LLMs_Helper\Tests\Helpers\Plugin_Helpers;
use PinkCrab\LLMs_Helper\Content\Default_Inclusion_Policy;

class Test_Default_Inclusion_Policy extends WP_UnitTestCase {

	use Plugin_Helpers;

	private Fake_Settings $settings;
	private Fixed_Noindex $noindex;
	private Fixed_Access $access;

	public function set_up(): void {
		parent::set_up();
		$this->set_permalink_structure( '/%postname%/' );
		$this->unhook_plugin_post_events();
		$this->settings = new Fake_Settings();
		$this->noindex  = new Fixed_Noindex();
		$this->access   = new Fixed_Access();
	}

	private function policy(): Default_Inclusion_Policy {
		return new Default_Inclusion_Policy( $this->settings, $this->noindex, $this->access );
	}

	private function page( array $args = array() ): WP_Post {
		return self::factory()->post->create_and_get(
			array_merge(
				array(
					'post_type'   => 'page',
					'post_status' => 'publish',
				),
				$args
			)
		);
	}

	public function test_published_page_is_included(): void {
		$this->assertTrue( $this->policy()->includes( $this->page() ) );
	}

	/**
	 * @return array<string, array{array<string, mixed>}>
	 */
	public function not_public_provider(): array {
		return array(
			'draft'    => array( array( 'post_status' => 'draft' ) ),
			'private'  => array( array( 'post_status' => 'private' ) ),
			'password' => array( array( 'post_password' => 'secret' ) ),
		);
	}

	/**
	 * @dataProvider not_public_provider
	 */
	public function test_posts_that_are_not_public_are_excluded( array $args ): void {
		$this->assertFalse( $this->policy()->includes( $this->page( $args ) ) );
	}

	public function test_post_type_not_in_settings_is_excluded(): void {
		$this->settings->post_types = array( 'post' );

		$this->assertFalse( $this->policy()->includes( $this->page() ) );
	}

	public function test_post_type_that_is_not_viewable_is_excluded(): void {
		register_post_type( 'llms_helper_hidden', array( 'public' => false ) );
		$this->settings->post_types = array( 'llms_helper_hidden' );

		$this->assertFalse( $this->policy()->includes( $this->page( array( 'post_type' => 'llms_helper_hidden' ) ) ) );

		unregister_post_type( 'llms_helper_hidden' );
	}

	public function test_excluded_post_is_excluded(): void {
		$page = $this->page();
		update_post_meta( $page->ID, Default_Inclusion_Policy::META_EXCLUDE, 1 );

		$this->assertFalse( $this->policy()->includes( $page ) );
	}

	public function test_plain_permalinks_exclude_everything(): void {
		$page = $this->page();
		$this->set_permalink_structure( '' );

		$this->assertFalse( $this->policy()->includes( $page ) );
	}

	public function test_woocommerce_noindex_pages_are_excluded(): void {
		$checkout = $this->page();
		$policy   = new class( $this->settings, $this->noindex, $this->access, $checkout->ID ) extends Default_Inclusion_Policy {
			public function __construct( Fake_Settings $settings, Fixed_Noindex $noindex, Fixed_Access $access, private int $checkout_id ) {
				parent::__construct( $settings, $noindex, $access );
			}
			protected function noindex_shop_page_ids(): array {
				return array( $this->checkout_id );
			}
		};

		$this->assertFalse( $policy->includes( $checkout ) );
		$this->assertTrue( $policy->includes( $this->page() ) );
	}

	public function test_no_shop_pages_are_excluded_without_woocommerce(): void {
		$this->assertFalse( function_exists( 'wc_get_page_id' ) );
		$this->assertTrue( $this->policy()->includes( $this->page() ) );
	}

	/**
	 * @return array<string, array{list<string>, bool}>
	 */
	public function product_visibility_provider(): array {
		return array(
			'visible'     => array( array(), true ),
			'featured'    => array( array( 'featured' ), true ),
			'shop only'   => array( array( 'exclude-from-search' ), false ),
			'search only' => array( array( 'exclude-from-catalog' ), false ),
			'hidden'      => array( array( 'exclude-from-search', 'exclude-from-catalog' ), false ),
		);
	}

	/**
	 * @dataProvider product_visibility_provider
	 */
	public function test_products_hidden_from_the_shop_or_search_are_excluded( array $terms, bool $included ): void {
		register_post_type( 'product', array( 'public' => true ) );
		register_taxonomy( 'product_visibility', 'product', array( 'public' => false ) );
		$this->settings->post_types = array( 'product' );
		$product                    = $this->page( array( 'post_type' => 'product' ) );
		wp_set_post_terms( $product->ID, $terms, 'product_visibility' );

		$this->assertSame( $included, $this->policy()->includes( $product ) );

		unregister_taxonomy( 'product_visibility' );
		unregister_post_type( 'product' );
	}

	public function test_coming_soon_options_are_ignored_without_woocommerce(): void {
		update_option( 'woocommerce_coming_soon', 'yes' );
		update_option( 'woocommerce_store_pages_only', 'no' );

		$this->assertTrue( $this->policy()->includes( $this->page() ) );
	}

	public function test_noindex_post_is_excluded(): void {
		$this->noindex->noindex = true;

		$this->assertFalse( $this->policy()->includes( $this->page() ) );
	}

	public function test_post_restricted_to_members_is_excluded(): void {
		$this->access->restricted = true;

		$this->assertFalse( $this->policy()->includes( $this->page() ) );
	}

	public function test_filter_can_exclude(): void {
		add_filter( 'llms_helper_include_post', '__return_false' );

		$this->assertFalse( $this->policy()->includes( $this->page() ) );
	}

	public function test_filter_can_include_and_gets_the_post(): void {
		$draft = $this->page( array( 'post_status' => 'draft' ) );
		add_filter(
			'llms_helper_include_post',
			static function ( bool $included, WP_Post $post ) use ( $draft ): bool {
				return $included || $post->ID === $draft->ID;
			},
			10,
			2
		);

		$this->assertTrue( $this->policy()->includes( $draft ) );
	}
}
