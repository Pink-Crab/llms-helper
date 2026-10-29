<?php
/**
 * PHPStan declarations for the SEO plugin entry points Seo_Plugin_Noindex, Seo_Plugin_Description and the sitemap
 * classes use. Never loaded.
 *
 * @package PinkCrab\LLMs_Helper\Tests
 */

namespace RankMath {

	class Helper {

		/**
		 * Rank Math 1.0.280, includes/helpers/class-post-type.php.
		 *
		 * @param int  $post_id                    Post ID.
		 * @param bool $should_check_excluded_list Whether to check the sitemap exclusions.
		 * @return bool
		 */
		public static function is_post_indexable( $post_id, $should_check_excluded_list = true ) {}

		/**
		 * Rank Math 1.0.280, includes/helpers/class-conditional.php.
		 *
		 * @return bool
		 */
		public static function is_invalid_registration() {}
	}
}

namespace RankMath\Paper {

	class Singular {

		/**
		 * Rank Math 1.0.280, includes/frontend/paper/class-singular.php.
		 *
		 * @param \WP_Post $post Post.
		 * @return void
		 */
		public function set_object( $post ) {}

		/**
		 * @return string
		 */
		public function description() {}
	}

	class Blog {

		/**
		 * Rank Math 1.0.280, includes/frontend/paper/class-blog.php.
		 *
		 * @return string
		 */
		public function description() {}
	}
}

namespace SlimSEO\MetaTags {

	class Description {

		/**
		 * Slim SEO 4.11.3, src/MetaTags/Description.php.
		 *
		 * @param int $post_id Post ID.
		 * @return string
		 */
		public function get_rendered_singular_value( int $post_id = 0 ): string {}
	}
}

namespace AIOSEO\Plugin\Common\Meta {

	class Robots {

		/**
		 * AIOSEO 5.0.3, app/Common/Meta/Robots.php.
		 *
		 * @param \WP_Post|null $post Post.
		 * @return void
		 */
		public function post( $post = null ) {}

		/**
		 * AIOSEO 5.0.3, app/Common/Meta/Robots.php.
		 *
		 * @param bool $array Return the attributes as an array.
		 * @return array<string, string>|string
		 */
		public function metaHelper( $array = false ) {}
	}
}

namespace {

	/**
	 * SEOPress 10.3, seopress-functions.php.
	 *
	 * @param string $service Service name.
	 * @return mixed
	 */
	function seopress_get_service( $service ) {}

	/**
	 * SEOPress 10.3, seopress-functions.php.
	 *
	 * @param string $feature Module name.
	 * @return string|null
	 */
	function seopress_get_toggle_option( $feature ) {}

	/**
	 * AIOSEO 5.0.3, app/AIOSEO.php.
	 *
	 * @return mixed
	 */
	function aioseo() {}

	/**
	 * Rank Math 1.0.280, rank-math.php.
	 *
	 * @return mixed
	 */
	function rank_math() {}

	/**
	 * Yoast SEO 28.7, the plugin's surfaces.
	 *
	 * @return mixed
	 */
	function YoastSEO() {}

	/**
	 * Yoast SEO 28.7, inc/sitemaps/interface-sitemap-provider.php.
	 */
	interface WPSEO_Sitemap_Provider {

		/**
		 * @param string $type Sitemap type.
		 * @return bool
		 */
		public function handles_type( $type );

		/**
		 * @param int $max_entries Entries per sitemap.
		 * @return array<int, array<string, mixed>>
		 */
		public function get_index_links( $max_entries );

		/**
		 * @param string $type         Sitemap type.
		 * @param int    $max_entries  Entries per sitemap.
		 * @param int    $current_page Sitemap page.
		 * @return array<int, array<string, mixed>>
		 */
		public function get_sitemap_links( $type, $max_entries, $current_page );
	}

	class WPSEO_Sitemaps_Router {

		/**
		 * Yoast SEO 28.7, inc/sitemaps/class-sitemaps-router.php.
		 *
		 * @param string $page Sitemap file name.
		 * @return string
		 */
		public static function get_base_url( $page ) {}
	}

	class WPSEO_Sitemaps_Cache {

		/**
		 * Yoast SEO 28.7, inc/sitemaps/class-sitemaps-cache.php.
		 *
		 * @param string $type Sitemap type.
		 * @return void
		 */
		public static function invalidate( $type ) {}

		/**
		 * Yoast SEO 28.7, inc/sitemaps/class-sitemaps-cache.php.
		 *
		 * @return void
		 */
		public static function clear_queued() {}
	}
}

namespace RankMath\Sitemap {

	class Router {

		/**
		 * Rank Math 1.0.280, includes/modules/sitemap/class-router.php.
		 *
		 * @param string $page Sitemap file name.
		 * @return string
		 */
		public static function get_base_url( $page ) {}
	}

	class Cache_Watcher {

		/**
		 * Rank Math 1.0.280, includes/modules/sitemap/class-cache-watcher.php.
		 *
		 * @param string $type Sitemap type.
		 * @return void
		 */
		public static function invalidate( $type ) {}

		/**
		 * Rank Math 1.0.280, includes/modules/sitemap/class-cache-watcher.php.
		 *
		 * @return void
		 */
		public static function clear_queued() {}
	}
}

namespace RankMath\Sitemap\Providers {

	/**
	 * Rank Math 1.0.280, includes/modules/sitemap/providers/interface-provider.php.
	 */
	interface Provider {

		/**
		 * @param string $type Sitemap type.
		 * @return bool
		 */
		public function handles_type( $type );

		/**
		 * @param int $max_entries Entries per sitemap.
		 * @return array<int, array<string, mixed>>
		 */
		public function get_index_links( $max_entries );

		/**
		 * @param string $type         Sitemap type.
		 * @param int    $max_entries  Entries per sitemap.
		 * @param int    $current_page Sitemap page.
		 * @return array<int, array<string, mixed>>
		 */
		public function get_sitemap_links( $type, $max_entries, $current_page );
	}
}
