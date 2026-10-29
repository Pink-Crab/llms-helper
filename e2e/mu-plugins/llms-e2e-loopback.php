<?php
/**
 * E2E helper: in wp-env the site's public address, localhost and the port in .wp-env.json, only exists on the
 * host machine. Inside the containers the web server is the compose service, so Loopback_Content_Renderer's
 * request is sent there with the public Host header, through llms_helper_render_request. While
 * llms_e2e_break_loopback is set the request goes to a closed port, as on a site that cannot reach itself.
 * [llms_e2e_context] prints who rendered the content and in which kind of request.
 *
 * @package PinkCrab\LLMs_Helper\Tests
 */

add_shortcode(
	'llms_e2e_context',
	static function (): string {
		$request = defined( 'REST_REQUEST' ) && REST_REQUEST ? 'rest' : ( is_admin() ? 'admin' : 'front' );
		return 'CONTEXT:' . ( is_user_logged_in() ? 'member' : 'visitor' ) . '-' . $request;
	}
);

add_filter(
	'llms_helper_render_request',
	static function ( array $request ): array {
		if ( get_option( 'llms_e2e_break_loopback' ) ) {
			$request['url'] = 'http://127.0.0.1:9/';
			return $request;
		}

		$services = array(
			57891 => 'wordpress',
			57892 => 'tests-wordpress',
		);
		$home     = wp_parse_url( home_url() );
		$port     = (int) ( $home['port'] ?? 0 );
		if ( ! isset( $services[ $port ] ) ) {
			return $request;
		}

		$public                             = $home['scheme'] . '://' . $home['host'] . ':' . $port;
		$request['url']                     = 'http://' . $services[ $port ] . substr( $request['url'], strlen( $public ) );
		$request['args']['headers']['Host'] = $home['host'] . ':' . $port;

		return $request;
	}
);
