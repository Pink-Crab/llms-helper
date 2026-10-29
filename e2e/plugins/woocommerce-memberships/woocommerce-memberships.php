<?php
/**
 * Plugin Name: WooCommerce Memberships (E2E stand-in)
 * Description: WooCommerce Memberships is paid, so CI uses this. It reads the real plugin's wc_memberships_rules and _wc_memberships_force_public, as 1.28.3 does, and hides what it hides from a visitor with no membership, in its default "Hide content only" mode. Locally .wp-env.override.json maps the real plugin over it.
 * Version: 1.28.3-e2e
 *
 * @package PinkCrab\LLMs_Helper\Tests
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- WooCommerce Memberships' own function names.

/**
 * Whether an active rule of a type covers a post, matched as WC_Memberships_Rules::get_rules() does.
 *
 * @param string $rule_type content_restriction or product_restriction.
 * @param int    $post_id   Post ID.
 * @return bool
 */
function llms_e2e_wcm_has_rule( string $rule_type, int $post_id ): bool {
	$post_type = get_post_type( $post_id );

	foreach ( (array) get_option( 'wc_memberships_rules', array() ) as $rule ) {
		$rule = (array) $rule;
		if ( 'yes' !== ( $rule['active'] ?? '' ) || ( $rule['rule_type'] ?? '' ) !== $rule_type
			|| 'post_type' !== ( $rule['content_type'] ?? '' ) || ( $rule['content_type_name'] ?? '' ) !== $post_type ) {
			continue;
		}

		// Product rules only restrict viewing with the view access type.
		if ( 'product_restriction' === $rule_type && 'view' !== ( $rule['access_type'] ?? '' ) ) {
			continue;
		}

		// A rule with no objects covers every post of its type.
		$ids = array_map( 'intval', (array) ( $rule['object_ids'] ?? array() ) );
		if ( empty( $ids ) || in_array( $post_id, $ids, true ) ) {
			return true;
		}
	}

	return false;
}

/**
 * The post ID from what the real functions accept.
 *
 * @param mixed $post_id ID, WP_Post, WC_Product or null for the current post.
 * @return int
 */
function llms_e2e_wcm_post_id( $post_id ): int {
	if ( $post_id instanceof WP_Post ) {
		return $post_id->ID;
	}
	if ( $post_id instanceof WC_Product ) {
		return $post_id->get_id();
	}

	return (int) ( $post_id ? $post_id : get_the_ID() );
}

/**
 * Whether the post is set public, over any rule.
 *
 * @param int $post_id Post ID.
 * @return bool
 */
function llms_e2e_wcm_is_public( int $post_id ): bool {
	return 'yes' === get_post_meta( $post_id, '_wc_memberships_force_public', true );
}

/**
 * No one on the e2e site has a membership, so only admins see restricted content.
 *
 * @return bool
 */
function llms_e2e_wcm_has_access(): bool {
	return current_user_can( 'manage_options' );
}

/**
 * Whether a post's content is restricted.
 *
 * @param mixed $post_id ID, WP_Post or null for the current post.
 * @return bool
 */
function wc_memberships_is_post_content_restricted( $post_id = null ) {
	$post_id = llms_e2e_wcm_post_id( $post_id );

	return $post_id > 0 && llms_e2e_wcm_has_rule( 'content_restriction', $post_id ) && ! llms_e2e_wcm_is_public( $post_id );
}

/**
 * Whether viewing a product is restricted.
 *
 * @param mixed $post_id ID, WP_Post, WC_Product or null for the current post.
 * @return bool
 */
function wc_memberships_is_product_viewing_restricted( $post_id = null ) {
	$post_id = llms_e2e_wcm_post_id( $post_id );

	return $post_id > 0 && llms_e2e_wcm_has_rule( 'product_restriction', $post_id ) && ! llms_e2e_wcm_is_public( $post_id );
}

// A restricted post shows a notice in place of its content.
add_filter(
	'the_content',
	static function ( $content ) {
		$post_id    = (int) get_the_ID();
		$restricted = 'product' === get_post_type( $post_id )
			? wc_memberships_is_product_viewing_restricted( $post_id )
			: wc_memberships_is_post_content_restricted( $post_id );

		return $restricted && ! llms_e2e_wcm_has_access() ? '<div class="wc-memberships-restriction-message">This content is only available to members.</div>' : $content;
	},
	999
);

// [wcm_restrict] shows its content to members only.
add_action(
	'init',
	static function () {
		add_shortcode(
			'wcm_restrict',
			static function ( $atts, $content = null ) {
				return llms_e2e_wcm_has_access() ? do_shortcode( (string) $content ) : '';
			}
		);
	}
);
