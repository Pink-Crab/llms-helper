<?php
/**
 * Tests for Membership_Plugin_Access.
 *
 * @package PinkCrab\LLMs_Helper\Tests
 */

declare(strict_types=1);

namespace PinkCrab\LLMs_Helper\Tests\Content;

use WP_Post;
use WP_UnitTestCase;
use PinkCrab\LLMs_Helper\Tests\Helpers\Plugin_Helpers;
use PinkCrab\LLMs_Helper\Content\Membership_Plugin_Access;

class Test_Membership_Plugin_Access extends WP_UnitTestCase {

	use Plugin_Helpers;

	public function set_up(): void {
		parent::set_up();
		$this->unhook_plugin_post_events();
	}

	/**
	 * Access check where the given plugins are active and give fixed answers, recording the user each ran as.
	 *
	 * @param array<string, bool> $answers Restricted answer by plugin key.
	 */
	private function access( array $answers ): Membership_Plugin_Access {
		return new class( $answers ) extends Membership_Plugin_Access {
			/** @var list<int> */
			public array $ran_as = array();

			public function __construct( private array $answers ) {}

			protected function active( string $plugin ): bool {
				return array_key_exists( $plugin, $this->answers );
			}

			protected function check( string $plugin, WP_Post $post ): bool {
				$this->ran_as[] = get_current_user_id();
				return $this->answers[ $plugin ];
			}
		};
	}

	private function page(): WP_Post {
		return self::factory()->post->create_and_get( array( 'post_type' => 'page' ) );
	}

	public function test_nothing_is_restricted_without_membership_plugins(): void {
		$this->assertFalse( ( new Membership_Plugin_Access() )->restricted( $this->page() ) );
		$this->assertFalse( $this->access( array() )->restricted( $this->page() ) );
	}

	public function test_checks_run_as_a_visitor_and_the_user_is_restored(): void {
		$editor = self::factory()->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $editor );
		$access = $this->access(
			array(
				'pmpro'   => false,
				'members' => false,
			)
		);

		$this->assertFalse( $access->restricted( $this->page() ) );
		$this->assertSame( array( 0, 0 ), $access->ran_as );
		$this->assertSame( $editor, get_current_user_id() );
	}

	public function test_any_active_plugin_restricting_is_enough(): void {
		$this->assertTrue(
			$this->access(
				array(
					'members'                 => false,
					'woocommerce_memberships' => true,
				)
			)->restricted( $this->page() )
		);
	}
}
