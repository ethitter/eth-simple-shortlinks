<?php
/**
 * Plugin functionality.
 *
 * @package ETH_Simple_Shortlinks
 */

/**
 * Class ETH_Simple_Shortlinks.
 */
class ETH_Simple_Shortlinks {
	/**
	 * PLUGIN SETUP
	 */

	/**
	 * Singleton
	 *
	 * @var self
	 */
	private static $instance;

	/**
	 * Instantiate singleton
	 */
	public static function get_instance() {
		if ( ! is_a( self::$instance, __CLASS__ ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Dummy magic method
	 */
	public function __clone() {
		_doing_it_wrong( __FUNCTION__, esc_html__( 'Cheatin&#8217; uh?', 'eth-simple-shortlinks' ), '0.1' );
	}

	/**
	 * Dummy magic method
	 */
	public function __wakeup() {
		_doing_it_wrong( __FUNCTION__, esc_html__( 'Cheatin&#8217; uh?', 'eth-simple-shortlinks' ), '0.1' );
	}

	/**
	 * Dummy magic method
	 *
	 * @param string $name Method name.
	 * @param array  $args Method arguments.
	 * @return null
	 */
	public function __call( $name = '', $args = array() ) {
		return null;
	}

	/**
	 * Plugin name.
	 *
	 * @var string
	 */
	private $name = 'ETH Simple Shortlinks';

	/**
	 * Rewrite slug.
	 *
	 * @var string
	 */
	private $slug = 'p';

	/**
	 * Rewrite rule.
	 *
	 * @var string
	 */
	private $rewrite_rule;

	/**
	 * Rewrite rule match.
	 *
	 * @var string
	 */
	private $rewrite_match;

	/**
	 * Query variable.
	 *
	 * @var string
	 */
	private $qv = 'eth-shortlink';

	/**
	 * Is environment ready?
	 *
	 * @var bool
	 */
	private $plugin_supported = false;

	/**
	 * Post types supported by the plugin.
	 *
	 * @var array
	 */
	private $supported_post_types = array();

	/**
	 * Post statuses allowed to redirect to.
	 *
	 * @var array
	 */
	private $supported_post_statuses = array();

	/**
	 * Register plugin's setup action.
	 */
	private function __construct() {
		// Build rewrite parts using other class properties.
		$this->rewrite_rule  = '^' . $this->slug . '/([\d]+)/?$';
		$this->rewrite_match = 'index.php?p=$matches[1]&' . $this->qv . '=1';

		// Basic plugin actions.
		add_action( 'plugins_loaded', array( $this, 'action_plugins_loaded' ) );
		add_action( 'init', array( $this, 'action_init' ) );
	}

	/**
	 * Load plugin translations.
	 */
	public function action_plugins_loaded() {
		load_plugin_textdomain(
			'eth-simple-shortlinks',
			false,
			dirname( dirname( plugin_basename( __FILE__ ) ) ) . '/languages/'
		);
	}

	/**
	 * Verify plugin is supported, then register its functionality.
	 */
	public function action_init() {
		global $wp_rewrite;

		// Plugin won't work if site doesn't use pretty permalinks.
		if ( empty( $wp_rewrite->permalink_structure ) ) {
			add_action( 'admin_notices', array( $this, 'action_add_admin_notices' ) );
			return;
		}

		$this->plugin_supported = true;

		// Admin notices.
		add_action( 'admin_notices', array( $this, 'action_add_admin_notices' ) );

		// Register rewrite rule.
		add_rewrite_rule( $this->rewrite_rule, $this->rewrite_match, 'top' );

		// Request handling.
		add_action( 'wp_loaded', array( $this, 'filter_support' ) );
		add_filter( 'query_vars', array( $this, 'filter_query_vars' ) );
		add_action( 'parse_request', array( $this, 'action_parse_request' ) );

		// Shortlink overrides.
		add_filter( 'get_shortlink', array( $this, 'filter_get_shortlink' ), 10, 2 );
		add_action( 'admin_head-edit.php', array( $this, 'add_admin_header_assets' ) );
		add_filter( 'post_row_actions', array( $this, 'filter_row_actions' ), 10, 2 );
		add_filter( 'page_row_actions', array( $this, 'filter_row_actions' ), 10, 2 );
	}

	/**
	 * Display admin notices if plugin's requirements aren't met.
	 */
	public function action_add_admin_notices() {
		// Notices are only relevant if current user can get to the Permalinks and Plugins options screens.
		if ( ! current_user_can( 'manage_options' ) || ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		// Build notices.
		$message = '';

		if ( $this->plugin_supported ) {
			// Check option for the plugin's rule.
			// The `$wp_rewrite` global will include it in `extra_rules_top` even though it hasn't been saved to the DB, and therefore isn't really active.
			$rewrites = get_option( 'rewrite_rules' );

			if ( is_array( $rewrites ) && ! array_key_exists( $this->rewrite_rule, $rewrites ) ) {
				$message = sprintf(
					/* translators: 1: URL of permalink options page. */
					__(
						'Please visit the <a href="%1$s">Permalinks</a> settings page to refresh your permalinks. Doing so will add the rules this plugin requires.',
						'eth-simple-shortlinks'
					),
					admin_url( 'options-permalink.php' )
				);
			}
		} else {
			$message = sprintf(
				/* translators: 1: URL of permalink options page. */
				__(
					'Please enable <a href="%1$s">pretty permalinks</a>, otherwise disable this plugin as it is not compatible with "Plain" permalinks.',
					'eth-simple-shortlinks'
				),
				admin_url( 'options-permalink.php' )
			);
		}

		// Display a notice if one exists.
		if ( ! empty( $message ) ) {
			$message = sprintf(
				/* translators: 1: Plugin name, 2: Notice text. */
				__(
					'<strong>%1$s</strong>: %2$s',
					'eth-simple-shortlinks'
				),
				$this->name,
				$message
			);

			?>
			<div class="error">
				<p><?php echo wp_kses_post( $message ); ?></p>
			</div>
			<?php
		}
	}

	/**
	 * PLUGIN FUNCTIONALITY
	 */

	/**
	 * Allow filtering of supported statuses and types.
	 */
	public function filter_support() {
		/**
		 * Filters supported post statuses.
		 *
		 * @param array $statuses Supported statuses.
		 */
		$this->supported_post_statuses = apply_filters( 'eth_simple_shortlinks_allowed_post_statuses', array( 'publish', 'future' ) );

		/**
		 * Filters supported post types.
		 *
		 * @param array $types Post types.
		 */
		$this->supported_post_types = apply_filters( 'eth_simple_shortlinks_allowed_post_types', array( 'post', 'page' ) );
	}

	/**
	 * Add custom query var to those permitted, so it can be detected at `parse_request`.
	 *
	 * @param array $qv Registered query vars.
	 * @return array
	 */
	public function filter_query_vars( $qv ) {
		$qv[] = $this->qv;

		return $qv;
	}

	/**
	 * Catch this plugin's requests and issue redirects, otherwise WP
	 * will serve content at duplicate URLs.
	 *
	 * Allows invalid post IDs fall through to WP's 404 handler, or
	 * anything else that might intercede.
	 *
	 * @param WP $request WP object.
	 */
	public function action_parse_request( $request ) {
		$redirect = $this->get_redirect_for_request( $request );

		if ( null === $redirect ) {
			return;
		}

		/**
		 * URLs aren't validated in case plugins filter permalinks to point to external URLs.
		 *
		 * If validation is desired, hook into the `eth_simple_shortlinks_redirect_url` filter.
		 */
		// phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
		wp_redirect( $redirect->destination, $redirect->status_code );
		exit;
	}

	/**
	 * Parse a given WP object for its redirect destination.
	 *
	 * @param WP $request WP object.
	 * @return \stdClass|null
	 */
	public function get_redirect_for_request( $request ) {
		if ( ! isset( $request->query_vars[ $this->qv ], $request->query_vars['p'] ) ) {
			return null;
		}

		$post_object = get_post( $request->query_vars['p'] );

		if ( ! $post_object instanceof WP_Post ) {
			return null;
		}

		/**
		 * Filters if post type and status should be validated.
		 *
		 * @since 0.6
		 *
		 * @param bool     $validate   Perform validation.
		 * @param WP_Post $post_object Post being redirected to.
		 * @param WP      $request     WP object.
		 */
		if (
			apply_filters( 'eth_simple_shortlinks_verify_requested_post_support', true, $post_object, $request ) &&
			(
				! $this->is_supported_post_type( $post_object->post_type ) ||
				! $this->is_supported_post_status( $post_object->post_status )
			)
		) {
			return null;
		}

		/**
		 * Filters the redirect URL.
		 *
		 * @since 0.6
		 *
		 * @param string  $destination Redirect destination.
		 * @param WP_Post $post_object Post being redirected to.
		 * @param WP      $request     WP object.
		 */
		$destination = apply_filters( 'eth_simple_shortlinks_redirect_url', get_permalink( $post_object ), $post_object, $request );

		/**
		 * Filters the redirect status code.
		 *
		 * @since 0.6
		 *
		 * @param int     $status_code Redirect status code.
		 * @param string  $destination Redirect destination.
		 * @param WP_Post $post_object Post being redirected to.
		 * @param WP      $request     WP object.
		 */
		$status_code = (int) apply_filters( 'eth_simple_shortlinks_redirect_status', 301, $destination, $post_object, $request );

		if ( empty( $destination ) || empty( $status_code ) ) {
			return null;
		}

		return (object) compact( 'destination', 'status_code' );
	}

	/**
	 * Override shortlinks with this plugin's format.
	 *
	 * @param string $shortlink Short URL.
	 * @param int    $id        Post ID.
	 * @return string
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
	 * Header assets for shortlink in row actions.
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
	 * Provide the shortlink in row actions for easy access.
	 *
	 * @param array   $actions Row actions.
	 * @param WP_Post $post Post object.
	 * @return array
	 */
	public function filter_row_actions( $actions, $post ) {
		if ( ! $this->is_supported_post_type( get_post_type( $post ) ) || ! $this->is_supported_post_status( get_post_status( $post ) ) ) {
			return $actions;
		}

		$actions['shortlink'] = '<a href="' . esc_js( $this->get_shortlink( $post->ID ) ) . '">' . __( 'Shortlink', 'eth-simple-shortlinks' ) . '</a>';

		return $actions;
	}

	/**
	 * Check if given post type is supported.
	 *
	 * @param string $type Post type.
	 * @return bool
	 */
	private function is_supported_post_type( $type ) {
		return in_array( $type, $this->supported_post_types, true );
	}

	/**
	 * Check if given post status is supported.
	 *
	 * @param string $status Post status.
	 * @return bool
	 */
	private function is_supported_post_status( $status ) {
		return in_array( $status, $this->supported_post_statuses, true );
	}

	/**
	 * Utility method for building permalink.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	public function get_shortlink( $post_id ) {
		// Use Core's default when this plugin can't build a link.
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
 * Shortcut for using the shortlink outside of this plugin's considerations.
 *
 * @param int $post_id Post ID.
 * @return string
 */
function eth_simple_shortlinks_get( $post_id ) {
	if ( ! did_action( 'wp_loaded' ) ) {
		_doing_it_wrong(
			__FUNCTION__,
			wp_kses_post(
				__(
					'Shortlinks cannot be generated until after <code>wp_loaded</code>; this ensures that all post types are registered.',
					'eth-simple-shortlinks'
				)
			),
			'0.3'
		);

		return false;
	}

	return ETH_Simple_Shortlinks::get_instance()->get_shortlink( $post_id );
}
