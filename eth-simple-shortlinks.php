<?php
/*
Plugin Name: ETH Simple Shortlinks
Plugin URI: https://ethitter.com/plugins/
Description: Simple non-GET shortlinks using post IDs
Author: Erick Hitter
Version: 0.5
Author URI: https://ethitter.com/
Text Domain: eth_simple_shortlinks
Domain Path: /languages/

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
	public function __clone() { _doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; uh?' ), '0.1' ); }
	public function __wakeup() { _doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; uh?' ), '0.1' ); }
	public function __call( $name = '', $args = array() ) { unset( $name, $args ); return null; }

	/**
	 * Class properties
	 */
	private $name          = 'ETH Simple Shortlinks';
	private $slug          = 'p';
	private $rewrite_rule  = null;
	private $rewrite_match = null;
	private $qv            = 'eth-shortlink';

	private $plugin_supported = false;

	private $supported_post_types    = array();
	private $supported_post_statuses = array();

	/**
	 * Register plugin's setup action
	 */
	private function __construct() {
		// Build rewrite parts using other class properties
		$this->rewrite_rule  = '^' . $this->slug . '/([\d]+)/?$';
		$this->rewrite_match = 'index.php?p=$matches[1]&' . $this->qv . '=1';

		// Basic plugin actions
		add_action( 'plugins_loaded', array( $this, 'action_plugins_loaded' ) );
		add_action( 'init', array( $this, 'action_init' ) );
	}

	/**
	 * Load plugin translations
	 */
	public function action_plugins_loaded() {
		load_plugin_textdomain( 'eth_simple_shortlinks', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	}

	/**
	 * Verify plugin is supported, then register its functionality
	 */
	public function action_init() {
		global $wp_rewrite;

		// Plugin won't work if site doesn't use pretty permalinks
		if ( empty( $wp_rewrite->permalink_structure ) ) {
			add_action( 'admin_notices', array( $this, 'action_add_admin_notices' ) );
		} else {
			$this->plugin_supported = true;

			// Admin notices
			add_action( 'admin_notices', array( $this, 'action_add_admin_notices' ) );

			// Register rewrite rule
			add_rewrite_rule( $this->rewrite_rule, $this->rewrite_match, 'top' );

			// Request handling
			add_action( 'wp_loaded', array( $this, 'filter_support' ) );
			add_filter( 'query_vars', array( $this, 'filter_query_vars' ) );
			add_action( 'parse_request', array( $this, 'action_parse_request' ) );

			// Shortlink overrides
			add_filter( 'get_shortlink', array( $this, 'filter_get_shortlink' ), 10, 2 );
			add_action( 'admin_head-edit.php', array( $this, 'add_admin_header_assets' ) );
			add_filter( 'post_row_actions', array( $this, 'filter_row_actions' ), 10, 2 );
			add_filter( 'page_row_actions', array( $this, 'filter_row_actions' ), 10, 2 );
		}
	}

	/**
	 * Display admin notices if plugin's requirements aren't met
	 */
	public function action_add_admin_notices() {
		// Notices are only relevant if current user can get to the Permalinks and Plugins options screens
		if ( ! current_user_can( 'manage_options') || ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		// Build notices
		$message = '';

		if ( $this->plugin_supported ) {
			// Check option for the plugin's rule
			// The `$wp_rewrite` global will include it in `extra_rules_top` even though it hasn't been saved to the DB, and therefore isn't really active.
			$rewrites = get_option( 'rewrite_rules' );

			if ( is_array( $rewrites ) && ! array_key_exists( $this->rewrite_rule, $rewrites ) ) {
				$message = sprintf( __( 'Please visit the <a href="%1$s">Permalinks</a> settings page to refresh your permalinks. Doing so will add the rules this plugin requires.', 'eth_simple_shortlinks' ), admin_url( 'options-permalink.php' ) );
			}
		} else {
			$message = sprintf( __( 'Please enable <a href="%1$s">pretty permalinks</a>, otherwise disable this plugin as it is not compatible with "Plain" permalinks.', 'eth_simple_shortlinks' ), admin_url( 'options-permalink.php' ) );
		}

		// Display a notice if one exists
		if ( ! empty( $message ) ) {
			$message = sprintf( __( '<strong>%1$s</strong>: %2$s', 'eth_simple_shortlinks' ), $this->name, $message );

			?><div class="error">
				<p><?php echo $message; ?></p>
			</div><?php
		}
	}

	/**
	 * PLUGIN FUNCTIONALITY
	 */

	/**
	 * Allow filtering of supported statuses and types
	 */
	public function filter_support() {
		$this->supported_post_statuses = apply_filters( 'eth_simple_shortlinks_allowed_post_statuses', array( 'publish', 'future' ) );
		$this->supported_post_types    = apply_filters( 'eth_simple_shortlinks_allowed_post_types',    array( 'post', 'page' ) );
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
	 * Allows invalid post IDs fall through to WP's 404 handler, or anything else that might intercede
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

		if ( ! $this->is_supported_post_status( get_post_status( $id ) ) ) {
			return $shortlink;
		}

		if ( ! $this->is_supported_post_type( get_post_type( $id ) ) ) {
			return $shortlink;
		}

		return $this->get_shortlink( $id );
	}

	/**
	 * Header assets for shortlink in row actions
	 */
	public function add_admin_header_assets() {
		global $typenow;
		if ( ! $this->is_supported_post_type( $typenow ) ) {
			return;
		}

		?>
		<script type="text/javascript">

			jQuery( document ) .ready( function( $ ) {
				$( '.row-actions .shortlink a' ).click( function( e ) {
					e.preventDefault();

					prompt( 'URL:', $( this ).attr('href') );
				} );
			} );
		</script>
		<?php
	}

	/**
	 * Provide the shortlink in row actions for easy access
	 */
	public function filter_row_actions( $actions, $post ) {
		if ( ! $this->is_supported_post_type( get_post_type( $post ) ) || ! $this->is_supported_post_status( get_post_status( $post ) ) ) {
			return $actions;
		}

		$actions['shortlink'] = '<a href="' . esc_js( $this->get_shortlink( $post->ID ) ) . '">' . __( 'Shortlink' ) . '</a>';

		return $actions;
	}

	/**
	 * Check if given post type is supported
	 */
	private function is_supported_post_type( $type ) {
		return in_array( $type, $this->supported_post_types );
	}

	/**
	 * Check if given post status is supported
	 */
	private function is_supported_post_status( $status ) {
		return in_array( $status, $this->supported_post_statuses );
	}

	/**
	 * Utility method for building permlink
	 */
	public function get_shortlink( $post_id ) {
		// Use Core's default when this plugin can't build a link
		if ( ! $this->plugin_supported ) {
			return wp_get_shortlink( $post_id );
		}

		return user_trailingslashit( home_url( sprintf( '%s/%d', $this->slug, $post_id ) ) );
	}
}

/**
 * One instance to rule them all
 */
ETH_Simple_Shortlinks::get_instance();

/**
 * Shortcut for using the shortlink outside of this plugin's considerations
 */
function eth_simple_shortlinks_get( $post_id ) {
	if ( ! did_action( 'wp_loaded' ) ) {
		_doing_it_wrong( __FUNCTION__, __( 'Shortlinks cannot be generated until after <code>wp_loaded</code>; this ensures that all post types are registered.', 'eth_simple_shortlinks' ), '0.3' );
		return false;
	}

	return ETH_Simple_Shortlinks::get_instance()->get_shortlink( $post_id );
}
