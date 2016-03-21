<?php
/*
Plugin Name: ETH Simple Shortlinks
Plugin URI: https://ethitter.com/plugins/
Description: Simple non-GET shortlinks using post IDs
Author: Erick Hitter
Version: 0.2
Author URI: https://ethitter.com/

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

class ETH_Simple_Shortlinks {
	/**
	 * PLUGIN SETUP
	 */

	/**
	 * Singleton
	 */
	private static $instance = null;

	/**
	 * Instantiate singleton
	 */
	public static function get_instance() {
		if ( ! is_a( self::$instance, __CLASS__ ) ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	/**
	 * Dummy magic methods
	 */
	public function __clone() {_doing_it_wrong( __FUNCTION__, 'Cheatin&#8217; huh?', '0.1' ); }
	public function __wakeup() { _doing_it_wrong( __FUNCTION__, 'Cheatin&#8217; huh?', '0.1' ); }
	public function __call( $name = '', $args = array() ) { unset( $name, $args ); return null; }

	/**
	 * Class properties
	 */
	private $slug = 'p';
	private $qv   = 'eth-shortlink';

	/**
	 *
	 */
	private function __construct() {
		// Request
		add_action( 'init', array( $this, 'add_rewrite_rule' ) );
		add_filter( 'query_vars', array( $this, 'filter_query_vars' ) );
		add_action( 'parse_request', array( $this, 'action_parse_request' ) );

		// Shortlink
		add_filter( 'get_shortlink', array( $this, 'filter_get_shortlink' ), 10, 2 );
	}

	/**
	 * Register rewrite rule
	 */
	public function add_rewrite_rule() {
		add_rewrite_rule( '^' . $this->slug . '/([\d]+)/?$', 'index.php?p=$matches[1]&' . $this->qv . '=1', 'top' );
	}

	/**
	 * Add custom query var to those permitted, so it can be detected at `parse_request`
	 */
	public function filter_query_vars( $qv ) {
		$qv[] = $this->qv;

		return $qv;
	}

	/**
	 * Catch this plugin's requests and issue redirects, otherwise WP will serve content at duplicate URLs
	 *
	 * Let's invalid post IDs fall through to WP's 404 handler, or anything else that might intercede
	 *
	 * URLs aren't validated in case plugins filter permalinks to point to external URLs
	 */
	public function action_parse_request( $request ) {
		if ( isset( $request->query_vars[ $this->qv ] ) ) {
			$dest = get_permalink( $request->query_vars[ 'p' ] );

			if ( $dest ) {
				wp_redirect( $dest, 301 );
				exit;
			}
		}
	}

	/**
	 * Override shortlinks with this plugin's format
	 */
	public function filter_get_shortlink( $shortlink, $id ) {
		if ( empty( $id ) ) {
			$_p = get_post();

			if ( ! empty( $_p->ID ) ) {
				$id = $_p->ID;
			}
		}

		if ( empty( $id ) ) {
			return $shortlink;
		}

		if ( ! in_array( get_post_status( $id ), apply_filters( 'eth_simple_shortlinks_allowed_post_statuses', array( 'publish', 'future' ) ) ) ) {
			return $shortlink;
		}

		if ( ! in_array( get_post_type( $id ), apply_filters( 'eth_simple_shortlinks_allowed_post_types', array( 'post', 'page' ) ) ) ) {
			return $shortlink;
		}

		return user_trailingslashit( home_url( sprintf( '%s/%d', $this->slug, $id ) ) );
	}
}

ETH_Simple_Shortlinks::get_instance();
