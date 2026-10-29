<?php
/**
 * PHPStan declarations for the membership plugin entry points Membership_Plugin_Access calls. Never loaded.
 *
 * @package PinkCrab\LLMs_Helper\Tests
 */

namespace ContentControl {

	/**
	 * Content Control 2.7.4, inc/functions/developers.php.
	 *
	 * @param int|null $content_id Post ID.
	 * @return bool
	 */
	function content_is_restricted( $content_id = null ) {}
}

namespace {

	/**
	 * Paid Memberships Pro 3.8.9, includes/content.php.
	 *
	 * @param int|null $post_id                  Post ID.
	 * @param int|null $user_id                  User ID, the current user when empty.
	 * @param bool     $return_membership_levels Return the levels as well.
	 * @return bool|array<int, mixed>
	 */
	function pmpro_has_membership_access( $post_id = null, $user_id = null, $return_membership_levels = false ) {}

	/**
	 * Members 3.2.26, inc/template.php.
	 *
	 * @param int        $user_id User ID.
	 * @param int|string $post_id Post ID.
	 * @return bool
	 */
	function members_can_user_view_post( $user_id, $post_id = '' ) {}

	/**
	 * Members 3.2.26, inc/functions-private-site.php.
	 *
	 * @return bool
	 */
	function members_is_private_blog() {}

	/**
	 * Ultimate Member 2.14.0, the plugin instance.
	 *
	 * @return mixed
	 */
	function UM() {}

	/**
	 * Restrict Content 4.0.5, core/includes/member-functions.php.
	 *
	 * @param int $user_id User ID, the current user when empty.
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	function rcp_user_can_access( $user_id = 0, $post_id = 0 ) {}

	/**
	 * LifterLMS 10.3.2, includes/functions/llms.functions.access.php.
	 *
	 * @param int      $post_id Post ID.
	 * @param int|null $user_id User ID.
	 * @return int|false Restricting membership ID.
	 */
	function llms_is_post_restricted_by_membership( $post_id, $user_id = null ) {}

	/**
	 * LifterLMS 10.3.2, includes/functions/llms.functions.access.php.
	 *
	 * @param int      $post_id Post ID.
	 * @param int|null $user_id User ID.
	 * @return int|false Restricting membership ID.
	 */
	function llms_is_post_restricted_by_sitewide_membership( $post_id, $user_id = null ) {}

	/**
	 * LifterLMS 10.3.2, includes/models/model.llms.lesson.php.
	 */
	class LLMS_Lesson {

		/**
		 * @param int|\WP_Post $model Post.
		 * @param array<mixed> $args  Arguments.
		 */
		public function __construct( $model, $args = array() ) {}

		/**
		 * @return bool
		 */
		public function is_free() {}
	}

	/**
	 * WooCommerce Memberships 1.28.3, src/functions/wc-memberships-functions-restrictions.php.
	 *
	 * @param int|\WP_Post|null $post_id Post.
	 * @return bool
	 */
	function wc_memberships_is_post_content_restricted( $post_id = null ) {}

	/**
	 * WooCommerce Memberships 1.28.3, src/functions/wc-memberships-functions-restrictions.php.
	 *
	 * @param int|\WP_Post|null $post_id Product.
	 * @return bool
	 */
	function wc_memberships_is_product_viewing_restricted( $post_id = null ) {}
}
