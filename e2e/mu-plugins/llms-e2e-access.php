<?php
/**
 * WP-CLI helper for membership.spec.js: restricts a post to members where each membership plugin stores it.
 *
 * Usage:
 *   wp llms-e2e-access restrict <plugin> <post-id>
 *   wp llms-e2e-access gate <plugin>
 *   wp llms-e2e-access reset <plugin>
 *
 * @package PinkCrab\LLMs_Helper\Tests
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

/**
 * Restrict a post to members or logged in users.
 *
 * @param string $plugin  Plugin slug.
 * @param int    $post_id Post ID.
 */
function llms_e2e_access_restrict( string $plugin, int $post_id ): void {
	global $wpdb;

	switch ( $plugin ) {
		case 'paid-memberships-pro':
			$wpdb->insert(
				$wpdb->pmpro_membership_levels,
				array(
					'name'              => 'E2E level',
					'description'       => '',
					'confirmation'      => '',
					'billing_limit'     => 0,
					'expiration_number' => 0,
					'expiration_period' => '',
				)
			);
			$wpdb->insert(
				$wpdb->pmpro_memberships_pages,
				array(
					'membership_id' => $wpdb->insert_id,
					'page_id'       => $post_id,
				)
			);
			break;
		case 'members':
			members_add_post_role( $post_id, 'administrator' );
			break;
		case 'content-control':
			$restriction_id = wp_insert_post(
				array(
					'post_type'   => 'cc_restriction',
					'post_title'  => 'E2E restriction',
					'post_status' => 'publish',
				)
			);
			$rule           = array(
				'id'         => uniqid(),
				'type'       => 'rule',
				'name'       => 'content_is_selected_post',
				'notOperand' => false,
				'options'    => array( 'selected' => array( $post_id ) ),
			);
			$settings       = array(
				'userStatus'       => 'logged_in',
				'protectionMethod' => 'replace',
				'conditions'       => array(
					'logicalOperator' => 'and',
					'items'           => array(
						array(
							'id'    => uniqid(),
							'type'  => 'group',
							'query' => array(
								'logicalOperator' => 'or',
								'items'           => array( $rule ),
							),
						),
					),
				),
			);
			add_post_meta( $restriction_id, 'restriction_settings', wp_parse_args( $settings, \ContentControl\get_default_restriction_settings() ), true );
			break;
		case 'ultimate-member':
			update_post_meta(
				$post_id,
				'um_content_restriction',
				array(
					'_um_custom_access_settings' => 1,
					'_um_accessible'             => '2',
				)
			);
			break;
		case 'restrict-content':
			update_post_meta( $post_id, '_is_paid', 1 );
			break;
		case 'lifterlms':
			$membership_id = wp_insert_post(
				array(
					'post_type'   => 'llms_membership',
					'post_title'  => 'E2E membership',
					'post_status' => 'publish',
				)
			);
			update_post_meta( $post_id, '_llms_is_restricted', 'yes' );
			update_post_meta( $post_id, '_llms_restricted_levels', array( $membership_id ) );
			break;
		case 'woocommerce-memberships':
			$plan_id = wp_insert_post(
				array(
					'post_type'   => 'wc_membership_plan',
					'post_title'  => 'E2E plan',
					'post_status' => 'publish',
				)
			);
			$rules   = (array) get_option( 'wc_memberships_rules', array() );
			$rules[] = array(
				'id'                 => uniqid( 'rule_' ),
				'membership_plan_id' => $plan_id,
				'active'             => 'yes',
				'rule_type'          => 'content_restriction',
				'content_type'       => 'post_type',
				'content_type_name'  => 'post',
				'object_ids'         => array( $post_id ),
				'access_schedule'    => 'immediate',
			);
			update_option( 'wc_memberships_rules', $rules );
			break;
	}
}

/**
 * Close the whole site to logged out visitors, or open it again.
 *
 * @param string $plugin Plugin slug.
 * @param bool   $closed Close or open.
 */
function llms_e2e_access_gate( string $plugin, bool $closed ): void {
	switch ( $plugin ) {
		case 'members':
			$settings                 = (array) get_option( 'members_settings', array() );
			$settings['private_blog'] = $closed ? 1 : 0;
			update_option( 'members_settings', $settings );
			break;
		case 'ultimate-member':
			UM()->options()->update( 'accessible', $closed ? 2 : 0 );
			break;
	}
}

/**
 * Remove the levels, restrictions, memberships and plans this helper made.
 *
 * @param string $plugin Plugin slug.
 */
function llms_e2e_access_reset( string $plugin ): void {
	global $wpdb;

	$types = array(
		'content-control'         => 'cc_restriction',
		'lifterlms'               => 'llms_membership',
		'woocommerce-memberships' => 'wc_membership_plan',
	);

	if ( isset( $types[ $plugin ] ) ) {
		$ids = get_posts(
			array(
				'post_type'   => $types[ $plugin ],
				'post_status' => 'any',
				'numberposts' => -1,
				'fields'      => 'ids',
			)
		);
		foreach ( $ids as $id ) {
			wp_delete_post( (int) $id, true );
		}
	}

	if ( 'woocommerce-memberships' === $plugin ) {
		delete_option( 'wc_memberships_rules' );
	}

	if ( 'paid-memberships-pro' === $plugin ) {
		$wpdb->query( "DELETE FROM {$wpdb->pmpro_memberships_pages}" );
		$wpdb->query( "DELETE FROM {$wpdb->pmpro_membership_levels} WHERE name = 'E2E level'" );
	}

	llms_e2e_access_gate( $plugin, false );
}

WP_CLI::add_command(
	'llms-e2e-access',
	static function ( array $args ): void {
		$action = $args[0] ?? '';
		$plugin = $args[1] ?? '';

		switch ( $action ) {
			case 'restrict':
				llms_e2e_access_restrict( $plugin, (int) ( $args[2] ?? 0 ) );
				break;
			case 'gate':
				llms_e2e_access_gate( $plugin, true );
				break;
			case 'reset':
				llms_e2e_access_reset( $plugin );
				break;
			default:
				WP_CLI::error( 'Use restrict, gate or reset.' );
		}

		WP_CLI::success( "{$action} {$plugin} done." );
	}
);
