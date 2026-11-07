<?php
/*  
 * RB 	Disable Comments
  * Version:           1.0.9 - 38451
 * Author:            RBS
 * Date:              03 02 2020 12:11:29 GMT
 */

if ( ! defined( 'WPINC' ) ) {
	die();
}


class classDisableComments {

	private static $instance = null;

	private $options;
	private $options_name = 'rb_disable_comments';
	private $modified_types = array();

	/** Is the plugin network-activated on this multisite install? */
	private $networkactive = false;
	/** Is this specific request writing to the network option instead of the per-site one? */
	private $saving_network = false;
	/** Are this site's own Disable-tab settings currently overridden by a network-wide enforce? */
	private $enforced = false;
	/** Abilities are hooked on two action names (core and feature plugin); register once. */
	private $abilities_registered = false;
	/** True while a Comments block's list of comments is being drawn (see the block-theme filters below). */
	private $in_comment_template = false;

	const NETWORK_OPTION_NAME = 'rb_disable_comments_network';

	/** Per user and per site: this admin closed the "not configured" notice. */
	const NOTICE_DISMISSED_OPTION = 'rb_disable_comments_notice_dismissed';

	/**
	 * The Disable tab's settings: all that a network-wide enforce takes over.
	 * Everything else in a row (General, AI Assistant, counters) stays the
	 * site's own, and a site's own values of these are never overwritten.
	 */
	const ENFORCED_KEYS = array(
		'remove_everywhere', 'disabled_post_types', 'block_new', 'existing_visibility',
		'date_rule', 'date_from', 'date_to', 'date_before', 'exempt_roles',
	);

	/**
	 * The PRO key is a plugin of its own and holds the PRO features' code; this
	 * plugin offers it the rb_disable_comments_* filters below. is_pro() only
	 * tells the settings screen what to show as unlocked.
	 */
	const PRO_KEY_CLASS = 'DisableCommentsRBProKey';

	public static function get_instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self;
		}
		return self::$instance;
	}
	
	public function __construct() {
		$this->networkactive = is_multisite() && array_key_exists(
			plugin_basename( RB_DISABLE_COMMENTS_PATH . 'disable-comments-rb.php' ),
			(array) get_site_option( 'active_sitewide_plugins', array() )
		);

		$this->enforced = $this->networkactive && (bool) apply_filters( 'rb_disable_comments_enforced', false );

		$this->options = $this->with_updated_site_defaults( $this->clean_row( get_option( $this->options_name, array() ) ) );

		// If the network admin has turned on "enforce network-wide", the
		// network's Disable settings replace the site's in memory, so all the
		// logic below that reads $this->options follows them. The site's row
		// keeps its own (save_options()).
		if ( $this->enforced ) {
			$network = $this->clean_row( get_site_option( self::NETWORK_OPTION_NAME, array() ) );
			$this->options = array_diff_key( $this->options, array_flip( self::ENFORCED_KEYS ) ) + array_intersect_key( $network, array_flip( self::ENFORCED_KEYS ) );
		}

		$this->hooks();

	}

	/**
	 * A settings row as it may really be stored: another plugin, an import or
	 * a hand edit can leave text or a plain list in it. What is not settings
	 * counts as nothing stored, and the next save puts the row right.
	 */
	private function clean_row( $row ) {
		return is_array( $row ) ? array_filter( $row, 'is_string', ARRAY_FILTER_USE_KEY ) : array();
	}

	/**
	 * A row written by 1.0.x has the flag and no key of the newer Disable tab.
	 * That admin never asked for the shortcuts 2.x added, so they stay off
	 * until turned on in General. New installs (no row) get them. Applied on
	 * read: an update rewrites nothing, and the next save stores these as they are.
	 */
	private function with_updated_site_defaults( $options ) {
		if ( array_key_exists( 'remove_everywhere', $options ) && ! array_key_exists( 'block_new', $options ) ) {
			$options += array(
				'show_dashboard_widget'     => false,
				'show_comments_page_button' => false,
				'show_admin_bar_menu'       => false,
			);
		}
		return $options;
	}


	/**
	 * True while the current admin-ajax request is acting on behalf of
	 * the Network Admin screen. is_network_admin() is always false during
	 * DOING_AJAX, so the network settings page appends ?is_network_admin=1
	 * to its ajaxUrl (see enqueue_network_settings_assets) and this checks
	 * that instead — a GET param is forgeable, so every save handler that
	 * calls this ALSO gates on current_user_can('manage_network_options').
	 */
	private function is_network_admin_ajax_context() {
		if ( ! $this->networkactive ) {
			return false;
		}
		if ( is_network_admin() ) {
			return true;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- this is a routing flag only (which options row to read/write), not itself an authorization check; every caller separately gates on current_user_can( 'manage_network_options' ) and its own AJAX nonce.
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX && isset( $_GET['is_network_admin'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['is_network_admin'] ) ) ) {
			return true;
		}
		return false;
	}

	private function save_options() {
		if ( $this->saving_network ) {
			update_site_option( self::NETWORK_OPTION_NAME, $this->options );
		} elseif ( $this->enforced ) {
			// $this->options holds the network's Disable settings: they must
			// not end up in this site's row, or its own are lost for good
			// when enforcement is turned off.
			$own = $this->clean_row( get_option( $this->options_name, array() ) );
			update_option(
				$this->options_name,
				array_diff_key( $this->options, array_flip( self::ENFORCED_KEYS ) ) + array_intersect_key( $own, array_flip( self::ENFORCED_KEYS ) )
			);
		} else {
			update_option( $this->options_name, $this->options );
		}
	}

	/**
	 * Runs $callback with $this->options pointed at the network-wide row
	 * instead of this site's own, so the same apply_disable_settings() /
	 * ajax_save_general() etc. can serve both the per-site and the
	 * Network Admin screens without duplicating their logic. Restores
	 * the site's own options afterward so nothing leaks into the rest of
	 * the request.
	 */
	private function with_network_options( $callback ) {
		$backup_options = $this->options;
		$backup_saving  = $this->saving_network;

		$this->options       = $this->clean_row( get_site_option( self::NETWORK_OPTION_NAME, array() ) );
		$this->saving_network = true;

		$result = $callback();

		$this->options       = $backup_options;
		$this->saving_network = $backup_saving;

		return $result;
	}

	/** Read-only accessor for WP-CLI (status command needs the raw options). */
	public function get_options() {
		return $this->options;
	}

	/** Are this site's Disable settings locked by the network? */
	public function is_enforced() {
		return $this->enforced;
	}

	public function is_network_active() {
		return $this->networkactive;
	}

	/** Stores settings of the key's features in the plugin's row. */
	public function update_settings( array $changes ) {
		$this->options = array_merge( $this->options, $changes );
		$this->save_options();
	}


	private function get_disabled_post_types() {
		// always a list: an option saved by an old version or edited by hand may hold a string
		return isset( $this->options['disabled_post_types'] ) ? array_values( array_filter( (array) $this->options['disabled_post_types'], 'is_string' ) ) : array();
	}


	const SETTINGS_SCREEN_ID = 'settings_page_rb_disable_comments_settings';

	private function hooks() {
		if( isset($this->options['remove_everywhere']) && $this->options['remove_everywhere'] ) {
			add_action( 'widgets_init', array( $this, 'disable_rc_widget' ) );
			add_filter( 'wp_headers', array( $this, 'filter_wp_headers' ) );
			add_action( 'template_redirect', array( $this, 'filter_query' ), 9 );	

			add_action( 'template_redirect', array( $this, 'filter_admin_bar' ) );
			add_action( 'admin_init', array( $this, 'filter_admin_bar' ) );
		}

		// XML-RPC / REST API comment endpoints. Independent of the
		// per-post-type toggles above — these block the *transport*, not
		// a content type, so they live in General Settings, not Disable.
		if ( ! empty( $this->options['remove_xmlrpc_comments'] ) ) {
			add_filter( 'xmlrpc_methods', array( $this, 'disable_xmlrpc_comment_methods' ) );
		}
		if ( ! empty( $this->options['remove_rest_comments'] ) ) {
			add_filter( 'rest_pre_insert_comment', array( $this, 'filter_rest_insert_comment' ), 10, 2 );
		}

		add_filter( 'debug_information', array( $this, 'add_site_health_info' ) );
		add_action( 'wp_loaded', array( $this, 'wp_load_hooks' ) );

		// AJAX handler for the Shadow-DOM settings screen. Registered
		// unconditionally (not inside wp_load_hooks' is_admin() branch)
		// because admin-ajax.php requests don't load admin_menu callbacks.
		add_action( 'wp_ajax_rb_disable_comments_save', array( $this, 'ajax_save_settings' ) );
		add_action( 'wp_ajax_rb_disable_comments_stats', array( $this, 'ajax_get_stats' ) );
		add_action( 'wp_ajax_rb_disable_comments_count', array( $this, 'ajax_count_comments' ) );
		add_action( 'wp_ajax_rb_disable_comments_delete', array( $this, 'ajax_delete_comments' ) );
		add_action( 'wp_ajax_rb_disable_comments_save_general', array( $this, 'ajax_save_general' ) );
		add_action( 'wp_ajax_rb_disable_comments_dismiss_dashboard', array( $this, 'ajax_dismiss_dashboard_widget' ) );
		add_action( 'wp_ajax_rb_disable_comments_dismiss_notice', array( $this, 'ajax_dismiss_notice' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_settings_assets' ) );

		// Abilities API (WP core feature still rolling out — both hooks
		// and both wp_register_* functions are guarded with
		// function_exists so this is a silent no-op everywhere else).
		add_action( 'wp_abilities_api_categories_init', array( $this, 'register_ability_categories' ) );
		// wp_abilities_api_init is the core (6.9+) name; abilities_api_init
		// is what earlier builds of the feature plugin fired.
		// register_abilities() runs once whichever fires.
		add_action( 'wp_abilities_api_init', array( $this, 'register_abilities' ) );
		add_action( 'abilities_api_init', array( $this, 'register_abilities' ) );
	}


	public function wp_load_hooks(){
		
		// Admin bar shows on the front-end too (for logged-in users), so
		// this stays outside the is_admin()/else split.
		if ( ! isset( $this->options['show_admin_bar_menu'] ) || $this->options['show_admin_bar_menu'] ) {
			add_action( 'admin_bar_menu', array( $this, 'register_admin_bar_menu' ), 100 );
		}

		// "Everywhere" is every type there is now, not the list stored on the
		// day of saving: a shop or theme added since must not open up.
		$disabled_post_types = ! empty( $this->options['remove_everywhere'] ) ? get_post_types() : $this->get_disabled_post_types();

		if( !empty( $disabled_post_types ) ) {
			foreach( $disabled_post_types as $type ) {
				if( post_type_supports( $type, 'comments' ) ) {
					$this->modified_types[] = $type;
					remove_post_type_support( $type, 'comments' );
					remove_post_type_support( $type, 'trackbacks' );
				}
			}
			add_filter( 'comments_array', 	array( $this, 'filter_existing_comments' ), 20, 2 );
			add_filter( 'comments_open', 	array( $this, 'filter_comment_status' ), 	20, 2 );
			add_filter( 'pings_open', 		array( $this, 'filter_comment_status' ), 	20, 2 );

			// A block theme draws comments with the Comments block, which
			// queries them itself and never reaches comments_array.
			add_filter( 'pre_render_block', array( $this, 'enter_comment_template' ), 99, 2 );
			add_filter( 'render_block', array( $this, 'leave_comment_template' ), 10, 2 );
			add_filter( 'the_comments', array( $this, 'filter_comment_template_comments' ), 10, 2 );
			add_filter( 'render_block_core/comments-title', array( $this, 'filter_comment_list_extras' ), 10, 2 );
			add_filter( 'render_block_core/comments-pagination', array( $this, 'filter_comment_list_extras' ), 10, 2 );

			// The same comments must not be readable by URL instead.
			add_filter( 'rest_comment_query', array( $this, 'filter_rest_comment_query' ) );
			add_filter( 'rest_prepare_comment', array( $this, 'filter_rest_prepare_comment' ), 10, 2 );
		}

		if ( is_admin() && $this->is_unconfigured() ) {
			add_action( 'all_admin_notices', array( $this, 'setup_notice' ) );
		}

		if( is_admin() ) {

			add_action( 'admin_menu', array( $this, 'settingsMenu' ) );
			add_filter( 'plugin_action_links', array( $this, 'plugin_actions_links'), 10, 2 );

			if ( ! isset( $this->options['show_dashboard_widget'] ) || $this->options['show_dashboard_widget'] ) {
				add_action( 'wp_dashboard_setup', array( $this, 'register_dashboard_widget' ) );
			}

			if ( ! isset( $this->options['show_comments_page_button'] ) || $this->options['show_comments_page_button'] ) {
				add_action( 'restrict_manage_comments', array( $this, 'render_comments_page_button' ) );
			}

			if ( $this->networkactive ) {
				add_action( 'network_admin_menu', array( $this, 'networkSettingsMenu' ) );
			}

			if( isset($this->options['remove_everywhere']) && $this->options['remove_everywhere'] ) {
				add_action( 'admin_menu', array( $this, 'filter_admin_menu' ), 9999 );
				add_action( 'admin_print_footer_scripts-index.php', array( $this, 'dashboard_js' ) );
				add_action( 'wp_dashboard_setup', array( $this, 'filter_dashboard' ) );
				add_filter( 'pre_option_default_pingback_flag', '__return_zero' );
			}
		} else {
			add_action( 'template_redirect', array( $this, 'check_comment_template' ) );

			if( isset($this->options['remove_everywhere']) && $this->options['remove_everywhere'] ) {
				add_filter( 'feed_links_show_comments_feed', '__return_false' );
				add_action( 'wp_footer', array( $this, 'hide_meta_widget_link' ), 100 );
			}
		}
	}

	public function check_comment_template() {
		if( is_singular() && ( isset($this->options['remove_everywhere']) && $this->options['remove_everywhere'] ) ) {
			wp_deregister_script( 'comment-reply' );
			remove_action( 'wp_head', 'feed_links_extra', 3 );
		}
	}

	public function empty_template_for_comments() { return ''; }


	public function filter_wp_headers( $headers ) {
		unset( $headers['X-Pingback'] );
		return $headers;
	}


	/**
	 * XML-RPC: removes only wp.newComment (comment creation). Reading
	 * comments via XML-RPC is left alone — this closes the spam vector,
	 * not the whole API.
	 */
	public function disable_xmlrpc_comment_methods( $methods ) {
		unset( $methods['wp.newComment'] );
		return $methods;
	}

	/**
	 * REST API: refuses comment creation inside the comments controller
	 * itself (rest_pre_insert_comment runs only in create_item, just before
	 * the insert). Matching on the route instead missed /wp/v2/Comments —
	 * routes match case-insensitively — and batch requests, and also blocked
	 * editing existing comments. Reading and editing are untouched — same
	 * "block the transport, not the read side" as the XML-RPC filter above.
	 * Block-editor notes (comment type "note", WP 6.9+) are an editorial
	 * tool, not public comments, so they pass.
	 */
	public function filter_rest_insert_comment( $prepared_comment, $request ) {
		if ( is_wp_error( $prepared_comment ) ) {
			return $prepared_comment;
		}
		$type = isset( $prepared_comment['comment_type'] ) ? $prepared_comment['comment_type'] : $request['type'];
		if ( 'note' === $type ) {
			return $prepared_comment;
		}
		return new WP_Error(
			'rb_disable_comments_rest_blocked',
			__( 'Comments are closed.', 'disable-comments-rb' ),
			array( 'status' => 403 )
		);
	}

	/**
	 * Tools → Site Health → Info, so a support request that includes a
	 * Site Health export shows this plugin's actual configuration instead
	 * of someone having to reproduce it by hand.
	 */
	public function add_site_health_info( $debug_info ) {
		$mode = ! empty( $this->options['remove_everywhere'] )
			? __( 'Disabled everywhere', 'disable-comments-rb' )
			: ( ! empty( $this->get_disabled_post_types() )
				? __( 'Disabled for selected content types', 'disable-comments-rb' )
				: __( 'Enabled (plugin inactive)', 'disable-comments-rb' ) );

		$debug_info['rb-disable-comments'] = array(
			'label'  => __( 'Disable Comments RB', 'disable-comments-rb' ),
			'fields' => array(
				'mode' => array(
					'label' => __( 'Comment disabling mode', 'disable-comments-rb' ),
					'value' => $mode,
				),
				'types' => array(
					'label' => __( 'Affected content types', 'disable-comments-rb' ),
					'value' => implode( ', ', (array) $this->get_disabled_post_types() ) ?: __( 'None', 'disable-comments-rb' ),
				),
				'block_new' => array(
					'label' => __( 'New comments blocked', 'disable-comments-rb' ),
					'value' => ( ! isset( $this->options['block_new'] ) || $this->options['block_new'] ) ? __( 'Yes', 'disable-comments-rb' ) : __( 'No', 'disable-comments-rb' ),
				),
				'existing_visibility' => array(
					'label' => __( 'Existing comments visibility', 'disable-comments-rb' ),
					'value' => isset( $this->options['existing_visibility'] ) ? $this->options['existing_visibility'] : 'none',
				),
				'xmlrpc' => array(
					'label' => __( 'XML-RPC comment posting blocked', 'disable-comments-rb' ),
					'value' => ! empty( $this->options['remove_xmlrpc_comments'] ) ? __( 'Yes', 'disable-comments-rb' ) : __( 'No', 'disable-comments-rb' ),
				),
				'rest' => array(
					'label' => __( 'REST API comment posting blocked', 'disable-comments-rb' ),
					'value' => ! empty( $this->options['remove_rest_comments'] ) ? __( 'Yes', 'disable-comments-rb' ) : __( 'No', 'disable-comments-rb' ),
				),
				'menu_location' => array(
					'label' => __( 'Settings menu location', 'disable-comments-rb' ),
					'value' => $this->get_menu_location(),
				),
			),
		);

		return $debug_info;
	}


	public function filter_query() {
		if( is_comment_feed() ) {
			wp_die( esc_html__( 'Comments are closed.', 'disable-comments-rb' ), '', array( 'response' => 403 ) );
		}
	}

	public function filter_admin_bar() {
		if( is_admin_bar_showing() ) {
			remove_action( 'admin_bar_menu', 'wp_admin_bar_comments_menu', 60 );
		}
	}

	
	/**
	 * True when the companion PRO key plugin is present and active.
	 * This is the ONLY gate — no license key, no remote check. Matches
	 * the model already used for Disable Right Click RB.
	 */
	public function is_pro() {
		return class_exists( self::PRO_KEY_CLASS );
	}

	private function get_menu_location() {
		return ( isset( $this->options['menu_location'] ) && 'top' === $this->options['menu_location'] ) ? 'top' : 'submenu';
	}

	private function settings_page_url() {
		if ( $this->networkactive && is_network_admin() ) {
			return add_query_arg( 'page', 'rb_disable_comments_settings', network_admin_url( 'settings.php' ) );
		}
		if ( 'top' === $this->get_menu_location() ) {
			return add_query_arg( 'page', 'rb_disable_comments_settings', admin_url( 'admin.php' ) );
		}
		return add_query_arg( 'page', 'rb_disable_comments_settings', admin_url( 'options-general.php' ) );
	}


	/**
	 * Nobody has chosen anything yet, so the "not configured" notice is due.
	 * Comments left on by choice ("off", or 1.0.x's "enable comments") are a
	 * saved row like any other. Under enforcement the choice is not this
	 * site's to make, and Network Admin saves the network's row, not a site's.
	 */
	private function is_unconfigured() {
		if ( $this->enforced ) {
			return false;
		}
		$row = is_network_admin() ? get_site_option( self::NETWORK_OPTION_NAME, array() ) : get_option( $this->options_name, array() );
		return ! $this->clean_row( $row );
	}

	public function setup_notice(){
		$screen = get_current_screen();
		if( $screen && strpos( $screen->id, '_page_rb_disable_comments_settings' ) !== false )
			return;

		if( ! current_user_can( 'manage_options' ) || get_user_option( self::NOTICE_DISMISSED_OPTION ) ) {
			return;
		}

		/* translators: %s: URL to the plugin's settings page. */
		$message = sprintf( __( 'The <em>Disable Comments</em> plugin is active, but isn\'t configured to do anything yet. Visit the <a href="%s">configuration page</a> to choose which post types to disable comments on.', 'disable-comments-rb' ), esc_url( $this->settings_page_url() ) );
		echo '<div id="rb-dc-setup-notice" class="notice notice-warning is-dismissible"><p>' . wp_kses_post( $message ) . '</p></div>';
		?>
		<script>
		// WordPress adds the close button and removes the notice; remembering it is ours.
		document.addEventListener( 'click', function ( event ) {
			var target = event.target;
			if ( ! target.closest || ! target.closest( '#rb-dc-setup-notice .notice-dismiss' ) ) { return; }
			var body = new URLSearchParams();
			body.set( 'action', 'rb_disable_comments_dismiss_notice' );
			body.set( 'nonce', '<?php echo esc_js( wp_create_nonce( 'rb-disable-comments-ajax' ) ); ?>' );
			fetch( ajaxurl, { method: 'POST', credentials: 'same-origin',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body.toString() } );
		} );
		</script>
		<?php
	}

	public function ajax_dismiss_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Not allowed.', 'disable-comments-rb' ) ), 403 );
		}
		check_ajax_referer( 'rb-disable-comments-ajax', 'nonce' );

		update_user_option( get_current_user_id(), self::NOTICE_DISMISSED_OPTION, 1 );

		wp_send_json_success();
	}


	public function filter_admin_menu(){
		global $pagenow;

		if ( $pagenow == 'comment.php' || $pagenow == 'edit-comments.php' || $pagenow == 'options-discussion.php' )
			wp_die( esc_html__( 'Comments are closed.', 'disable-comments-rb' ), '', array( 'response' => 403 ) );

		remove_menu_page( 'edit-comments.php' );
		remove_submenu_page( 'options-general.php', 'options-discussion.php' );
	}


	public function filter_dashboard(){
		remove_meta_box( 'dashboard_recent_comments', 'dashboard', 'normal' );
	}


	public function dashboard_js(){
		echo '<script>
		jQuery(function($){
			$("#dashboard_right_now .comment-count, #latest-comments").hide();
		 	$("#welcome-panel .welcome-comments").parent().hide();
		});
		</script>';
	}


	public function hide_meta_widget_link(){
		if ( is_active_widget( false, false, 'meta', true ) && wp_script_is( 'jquery', 'enqueued' ) ) {
			echo '<script> jQuery(function($){ $(".widget_meta a[href=\'' . esc_url( get_bloginfo( 'comments_rss2_url' ) ) . '\']").parent().remove(); }); </script>';
		}
	}


	/**
	 * Dashboard widget: a compact read of the same numbers the
	 * Statistics tab shows, plus a highlight reel of what's new in this
	 * release and a shortcut into the settings screen. Visibility is
	 * governed by options['show_dashboard_widget'] (default on) — the
	 * same flag the widget's own × button and the General Settings
	 * checkbox both read/write, so either one turning it off is
	 * reversible from the other.
	 */
	public function register_dashboard_widget() {
		// Same capability as the settings screen and the widget's own ×
		// (ajax_dismiss_dashboard_widget): the widget shows site-wide counts
		// (pending, spam) and carries the plugin's AJAX nonce, neither of
		// which a subscriber or author should get.
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		wp_add_dashboard_widget(
			'rb_disable_comments_dashboard',
			__( 'Disable Comments RB — Comment Statistics', 'disable-comments-rb' ),
			array( $this, 'render_dashboard_widget' )
		);
	}

	public function render_dashboard_widget() {
		$stats  = $this->compute_stats();
		$totals = $stats['totals'];
		$url    = $this->settings_page_url();
		$nonce  = wp_create_nonce( 'rb-disable-comments-ajax' );

		$features = array(
			__( 'Per-post-type comment disabling, plus independent controls for new vs. existing comments', 'disable-comments-rb' ),
			__( 'Hide existing comments by date range, with role-based exceptions for staff', 'disable-comments-rb' ),
			__( 'Bulk delete/trash by content type, status and date — with a Count-first safety step', 'disable-comments-rb' ),
			__( 'AI Assistant: keyword- and (for WooCommerce) star-rating-triggered auto-replies', 'disable-comments-rb' ),
			__( 'Multisite network enforcement, WP-CLI commands, and JSON export/import', 'disable-comments-rb' ),
			__( 'Blocks comment spam via XML-RPC and the REST API, with Site Health reporting', 'disable-comments-rb' ),
		);

		?>
		<style>
			#rb_disable_comments_dashboard .inside{padding:0;position:relative;}
			.rbdc-dash{padding:16px 20px 20px;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;}
			.rbdc-dash-close{position:absolute;top:10px;right:12px;background:none;border:0;cursor:pointer;
				color:#8a8494;font-size:18px;line-height:1;padding:4px;}
			.rbdc-dash-close:hover{color:#b3261e;}
			.rbdc-dash-pills{display:flex;flex-wrap:wrap;gap:10px;margin:4px 0 14px;}
			.rbdc-dash-pill{background:#f4eff9;border-radius:10px;padding:10px 14px;min-width:74px;}
			.rbdc-dash-num{display:block;font-size:19px;font-weight:600;color:#1d1b20;}
			.rbdc-dash-lbl{font-size:11.5px;color:#49454f;}
			.rbdc-dash-release{background:#12141c;color:#e7e4ef;border-radius:10px;padding:14px 16px;margin-top:14px;}
			.rbdc-dash-release-head{display:flex;align-items:center;gap:8px;margin-bottom:8px;}
			.rbdc-dash-dot{width:7px;height:7px;border-radius:50%;background:#f5c542;flex-shrink:0;}
			.rbdc-dash-release-title{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:#f5c542;}
			.rbdc-dash-release ul{margin:0 0 12px;padding-left:16px;font-size:12.5px;line-height:1.7;}
			.rbdc-dash-release ul li{margin-bottom:4px;}
			.rbdc-dash-btn{display:inline-block;background:#6750a4;color:#fff;text-decoration:none;font-size:13px;
				font-weight:500;border-radius:16px;padding:8px 16px;}
			.rbdc-dash-btn:hover{background:#5a4592;color:#fff;}
		</style>
		<div class="rbdc-dash">
			<button type="button" class="rbdc-dash-close" title="<?php esc_attr_e( 'Hide this widget (re-enable it anytime from Disable Comments RB → General)', 'disable-comments-rb' ); ?>">&times;</button>

			<div class="rbdc-dash-pills">
				<?php
				// wp_kses_post() is a recognised escaping sink for WPCS, and
				// dash_pill_html() only ever builds a div/span with values
				// already run through esc_html()/number_format_i18n() — the
				// wrap here is belt-and-braces, not the only thing sanitizing it.
				echo wp_kses_post( $this->dash_pill_html( $totals['active'] + $totals['pending'], __( 'Total', 'disable-comments-rb' ) ) );
				echo wp_kses_post( $this->dash_pill_html( $totals['active'], __( 'Active', 'disable-comments-rb' ) ) );
				echo wp_kses_post( $this->dash_pill_html( $totals['pending'], __( 'Pending', 'disable-comments-rb' ) ) );
				echo wp_kses_post( $this->dash_pill_html( $totals['spam'], __( 'Spam', 'disable-comments-rb' ) ) );
				?>
			</div>

			<div class="rbdc-dash-release">
				<div class="rbdc-dash-release-head">
					<span class="rbdc-dash-dot"></span>
					<span class="rbdc-dash-release-title"><?php esc_html_e( 'Disable Comments RB v2 — what\'s new', 'disable-comments-rb' ); ?></span>
				</div>
				<ul>
					<?php foreach ( $features as $f ) : ?>
						<li><?php echo esc_html( $f ); ?></li>
					<?php endforeach; ?>
				</ul>
				<a class="rbdc-dash-btn" href="<?php echo esc_url( $url ); ?>"><?php esc_html_e( 'Open plugin settings', 'disable-comments-rb' ); ?></a>
			</div>
		</div>
		<script>
		(function(){
			var closeBtn = document.querySelector( '#rb_disable_comments_dashboard .rbdc-dash-close' );
			if ( ! closeBtn ) { return; }
			closeBtn.addEventListener( 'click', function () {
				var widget = document.getElementById( 'rb_disable_comments_dashboard' );
				var body = new URLSearchParams();
				body.set( 'action', 'rb_disable_comments_dismiss_dashboard' );
				body.set( 'nonce', '<?php echo esc_js( $nonce ); ?>' );
				fetch( ajaxurl, { method: 'POST', credentials: 'same-origin',
					headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body.toString() } );
				if ( widget ) { widget.style.display = 'none'; }
			} );
		})();
		</script>
		<?php
	}

	/** Builds one dashboard-widget stat pill as an HTML string, values pre-escaped. */
	private function dash_pill_html( $num, $label ) {
		return sprintf(
			'<div class="rbdc-dash-pill"><span class="rbdc-dash-num">%s</span><span class="rbdc-dash-lbl">%s</span></div>',
			esc_html( number_format_i18n( (int) $num ) ),
			esc_html( $label )
		);
	}

	public function ajax_dismiss_dashboard_widget() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Not allowed.', 'disable-comments-rb' ) ), 403 );
		}
		check_ajax_referer( 'rb-disable-comments-ajax', 'nonce' );

		$this->options['show_dashboard_widget'] = false;
		$this->save_options();

		wp_send_json_success();
	}


	/**
	 * Comments are hidden per post type in $this->modified_types (set in
	 * wp_load_hooks() from disabled_post_types), not by the global
	 * remove_everywhere flag — that flag only decides whether *all* public
	 * types get added to modified_types.
	 *
	 * Two independent controls, matching what people actually ask for:
	 * - block_new: stop new comments being posted (comments_open/pings_open)
	 * - existing_visibility: what happens to comments already on the post
	 *   - 'all'        leave them showing
	 *   - 'none'       hide every existing comment (old default behaviour)
	 *   - 'date_range' hide existing comments outside a date window
	 */
	public function filter_existing_comments($comments, $post_id) {
		if ( ! $this->is_disabled_for_post( get_post( $post_id ) ) ) {
			return $comments;
		}

		$visibility = $this->existing_visibility();

		if ( 'all' === $visibility ) {
			return $comments;
		}

		if ( 'none' === $visibility ) {
			return array();
		}

		// a mode of the PRO key
		return apply_filters( 'rb_disable_comments_visible_comments', $comments, $visibility, $this->options );
	}


	public function filter_comment_status( $open, $post_id ) {
		if ( ! $this->is_disabled_for_post( get_post( $post_id ) ) ) {
			return $open;
		}

		// The PRO key's role exceptions.
		if ( apply_filters( 'rb_disable_comments_user_is_exempt', false ) ) {
			return $open;
		}

		$block_new = ! isset( $this->options['block_new'] ) || $this->options['block_new'];
		return $block_new ? false : $open;
	}

	/** Everywhere: any post, whatever its type and even when it cannot be loaded, as in 1.0.x. Otherwise the chosen types. */
	private function is_disabled_for_post( $post ) {
		if ( ! empty( $this->options['remove_everywhere'] ) ) {
			return true;
		}
		return $post && in_array( $post->post_type, $this->modified_types, true );
	}

	private function existing_visibility() {
		$visibility = isset( $this->options['existing_visibility'] ) ? $this->options['existing_visibility'] : 'none';
		// A mode nobody handles (the PRO key was removed after choosing it): fail to the safe default.
		return in_array( $visibility, $this->visibility_modes(), true ) ? $visibility : 'none';
	}

	private function visibility_modes() {
		return (array) apply_filters( 'rb_disable_comments_visibility_modes', array( 'all', 'none' ) );
	}

	/**
	 * The Comments block's list (core/comment-template): the_comments runs for
	 * every comment query on the page, so it is only acted on while this block
	 * is being drawn. A plugin short-circuiting the block means it never runs
	 * and leave_comment_template() would not be reached either.
	 */
	public function enter_comment_template( $pre_render, $parsed_block ) {
		if ( null === $pre_render && isset( $parsed_block['blockName'] ) && 'core/comment-template' === $parsed_block['blockName'] ) {
			$this->in_comment_template = true;
		}
		return $pre_render;
	}

	public function leave_comment_template( $content, $block ) {
		if ( isset( $block['blockName'] ) && 'core/comment-template' === $block['blockName'] ) {
			$this->in_comment_template = false;
		}
		return $content;
	}

	public function filter_comment_template_comments( $comments, $query ) {
		if ( ! $this->in_comment_template || ! is_array( $comments ) ) {
			return $comments;
		}
		$post_id = ! empty( $query->query_vars['post_id'] ) ? (int) $query->query_vars['post_id'] : get_the_ID();
		return $this->filter_existing_comments( $comments, $post_id );
	}

	/** The block's "2 responses" and its pages would give away comments that are not listed. */
	public function filter_comment_list_extras( $content, $block ) {
		$post_id = isset( $block['context']['postId'] ) ? (int) $block['context']['postId'] : get_the_ID();
		return $this->hides_all_existing_comments( get_post( $post_id ) ) ? '' : $content;
	}

	private function hides_all_existing_comments( $post ) {
		return 'none' === $this->existing_visibility() && $this->is_disabled_for_post( $post );
	}

	/**
	 * GET /wp/v2/comments for anyone who does not moderate comments: without
	 * what is hidden on the site. Moderators keep it, the admin screens need
	 * it. A date range is left to the pages: one query cannot express it per type.
	 */
	public function filter_rest_comment_query( $args ) {
		if ( current_user_can( 'moderate_comments' ) || 'none' !== $this->existing_visibility() ) {
			return $args;
		}
		if ( ! empty( $this->options['remove_everywhere'] ) ) {
			$args['post__in'] = array( 0 );
		} else {
			$args['post_type'] = array_values( array_diff( get_post_types(), $this->modified_types ) );
		}
		return $args;
	}

	/** GET /wp/v2/comments/<id>: the same, for one asked for by its number. */
	public function filter_rest_prepare_comment( $response, $comment ) {
		if ( ! current_user_can( 'moderate_comments' ) && $this->hides_all_existing_comments( get_post( $comment->comment_post_ID ) ) ) {
			return new WP_Error( 'rest_comment_invalid_id', __( 'Invalid comment ID.', 'disable-comments-rb' ), array( 'status' => 404 ) );
		}
		return $response;
	}

	/** role_slug => display name, straight from WP_Roles — no admin-only dependency. */
	public function get_available_roles() {
		$roles = array();
		foreach ( wp_roles()->roles as $slug => $role ) {
			$roles[ $slug ] = translate_user_role( $role['name'] );
		}
		return $roles;
	}


	public function disable_rc_widget() {
		unregister_widget( 'WP_Widget_Recent_Comments' );
	}


	public function plugin_actions_links( $links, $file ) {
		static $plugin;

		if( $file == 'disable-comments-rb/disable-comments-rb.php' && current_user_can('manage_options') ) {
			array_unshift(
				$links,
				sprintf( '<a href="%s">%s</a>', esc_attr( $this->settings_page_url() ), esc_html__( 'Settings', 'disable-comments-rb' ) )
			);
		}

		return $links;
	}

	
    public function pluginsListPage()
    {
        echo "empty page";
    }


	/**
	 * A shortcut button in the native Comments list's filter row — the
	 * standard hook plugins use to add controls there, so it sits right
	 * next to "All comment types" / "Filter" rather than needing its own
	 * page-title-action wiring (WP core has no clean hook for that on
	 * edit-comments.php specifically).
	 */
	public function render_comments_page_button() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		printf(
			'<a href="%1$s" class="button" style="margin-left:8px;">%2$s</a>',
			esc_url( $this->settings_page_url() ),
			esc_html__( 'Disable Comments RB Settings', 'disable-comments-rb' )
		);
	}

	/**
	 * Toolbar shortcut, next to whatever other rbPlugins toolbar items
	 * (RB Forms etc.) are already registered. Sub-items deep-link into a
	 * specific tab via ?rbdc_tab=, which assets/js/rb-admin-settings.js
	 * reads on load to activate that tab automatically.
	 */
	public function register_admin_bar_menu( $wp_admin_bar ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$base_url = $this->settings_page_url();

		$wp_admin_bar->add_node( array(
			'id'    => 'rb-disable-comments',
			'title' => '<span class="ab-icon dashicons dashicons-admin-comments" style="top:2px;"></span>' . __( 'Disable Comments RB', 'disable-comments-rb' ),
			'href'  => $base_url,
		) );

		$tabs = array(
			'disable' => __( 'Disable', 'disable-comments-rb' ),
			'delete'  => __( 'Delete', 'disable-comments-rb' ),
			'ai'      => __( 'AI Assistant', 'disable-comments-rb' ),
			'general' => __( 'General', 'disable-comments-rb' ),
			'stats'   => __( 'Statistics', 'disable-comments-rb' ),
		);
		foreach ( $tabs as $slug => $label ) {
			$wp_admin_bar->add_node( array(
				'id'     => 'rb-disable-comments-' . $slug,
				'parent' => 'rb-disable-comments',
				'title'  => $label,
				'href'   => add_query_arg( 'rbdc_tab', $slug, $base_url ),
			) );
		}
	}

	public function settingsMenu()
    {
        $title = __('Disable Comments RB', 'disable-comments-rb');

        if ( 'top' === $this->get_menu_location() ) {
            add_menu_page( $title, $title, 'manage_options', 'rb_disable_comments_settings', array( $this, 'options' ), 'dashicons-admin-comments', 80 );
        } else {
            add_submenu_page('options-general.php', $title, $title, 'manage_options', 'rb_disable_comments_settings', array($this, 'options'));
        }
    }

	/**
	 * Network Admin gets its own copy of the same settings screen (same
	 * options() render callback, same Shadow-DOM app) — enqueue_settings_assets()
	 * detects is_network_admin() and tells the JS to show only the
	 * Disable/General tabs (Delete and Statistics act on one site's
	 * wp_comments table, which has no meaning at the network level).
	 */
	public function networkSettingsMenu()
    {
        $title = __('Disable Comments RB', 'disable-comments-rb');
        add_submenu_page( 'settings.php', $title, $title, 'manage_network_options', 'rb_disable_comments_settings', array( $this, 'options' ) );
    }


	public function addAdminCss()
    {
        echo '<style>
        #adminmenu .toplevel_page_rb_plugins_settings > .wp-menu-image.dashicons-rest-api:before{
            color: #99d000;
        }
      </style>';
    }



	public function options() {
		include( RB_DISABLE_COMMENTS_PATH .'information.php');
		include( RB_DISABLE_COMMENTS_PATH .'options.php');
	}


	/**
	 * Returns [ post_type_slug => label ] for every post type that either
	 * natively supports comments, or is currently in our disabled list
	 * (so a type someone unregistered later doesn't silently vanish from
	 * the UI while still stored in options).
	 */
	public function get_selectable_post_types() {
		$disabled = $this->get_disabled_post_types();
		$types    = get_post_types( array( 'public' => true ), 'objects' );
		$result   = array();

		foreach ( $types as $slug => $obj ) {
			if ( post_type_supports( $slug, 'comments' ) || in_array( $slug, (array) $disabled, true ) ) {
				$result[ $slug ] = $obj->labels->name;
			}
		}
		return $result;
	}

	private function is_settings_screen() {
		if ( ! function_exists( 'get_current_screen' ) ) {
			return false;
		}
		$screen = get_current_screen();
		return $screen && strpos( $screen->id, '_page_rb_disable_comments_settings' ) !== false;
	}

	public function enqueue_settings_assets( $hook ) {
		if ( ! $this->is_settings_screen() ) {
			return;
		}

		wp_enqueue_script(
			'rb-disable-comments-admin',
			RB_DISABLE_COMMENTS_URL . 'assets/js/rb-admin-settings.js',
			array(),
			RB_DISABLE_COMMENTS_VERSION,
			true
		);

		$is_net_admin_screen = is_network_admin();

		$ajax_url = admin_url( 'admin-ajax.php' );
		if ( $is_net_admin_screen ) {
			// is_network_admin() is always false during DOING_AJAX, so the
			// AJAX handlers can't tell a network save from a site save by
			// context alone — this query param is how they find out (see
			// is_network_admin_ajax_context()).
			$ajax_url = add_query_arg( 'is_network_admin', '1', $ajax_url );
		}

		// On the Network Admin screen, show the network-wide row even if
		// "enforce" is currently off — a super admin needs to be able to
		// pre-configure defaults before flipping enforcement on. get_selectable_post_types()
		// is safe to call on $this either way; it only reads registered
		// post types, not the options row.
		$display_options = $is_net_admin_screen ? $this->clean_row( get_site_option( self::NETWORK_OPTION_NAME, array() ) ) : $this->options;
		$display_disabled_types = isset( $display_options['disabled_post_types'] ) ? (array) $display_options['disabled_post_types'] : array();

		if ( ! empty( $display_options['remove_everywhere'] ) ) {
			$current_mode = 'all';
		} elseif ( ! empty( $display_disabled_types ) ) {
			$current_mode = 'selected';
		} else {
			$current_mode = 'off';
		}

		wp_localize_script( 'rb-disable-comments-admin', 'RBDisableComments', array(
			'ajaxUrl'    => $ajax_url,
			'nonce'      => wp_create_nonce( 'rb-disable-comments-ajax' ),
			'mode'       => $current_mode,
			'postTypes'  => $this->get_selectable_post_types(),
			'availableRoles' => $this->get_available_roles(),
			'exemptRoles'    => isset( $display_options['exempt_roles'] ) ? array_values( (array) $display_options['exempt_roles'] ) : array(),
			'selected'   => array_values( $display_disabled_types ),
			'blockNew'   => ! isset( $display_options['block_new'] ) || $display_options['block_new'],
			'visibility' => isset( $display_options['existing_visibility'] ) ? $display_options['existing_visibility'] : 'none',
			'dateRule'   => isset( $display_options['date_rule'] ) ? $display_options['date_rule'] : 'between',
			'dateFrom'   => isset( $display_options['date_from'] ) ? $display_options['date_from'] : '',
			'dateTo'     => isset( $display_options['date_to'] ) ? $display_options['date_to'] : '',
			'dateBefore' => isset( $display_options['date_before'] ) ? $display_options['date_before'] : '',
			'menuLocation' => $this->get_menu_location(),
			'xmlrpcBlocked' => ! empty( $this->options['remove_xmlrpc_comments'] ),
			'restBlocked'   => ! empty( $this->options['remove_rest_comments'] ),
			'cliEnabled'    => $this->is_pro() && ( ! isset( $this->options['cli_enabled'] ) || $this->options['cli_enabled'] ),
			'showDashboardWidget' => ! isset( $this->options['show_dashboard_widget'] ) || $this->options['show_dashboard_widget'],
			'showCommentsPageButton' => ! isset( $this->options['show_comments_page_button'] ) || $this->options['show_comments_page_button'],
			'showAdminBarMenu' => ! isset( $this->options['show_admin_bar_menu'] ) || $this->options['show_admin_bar_menu'],
			'wooActive'     => class_exists( 'WooCommerce' ),
			'isPro'         => $this->is_pro(),
			'proBuyUrl'     => 'https://rbplugins.com/wordpress-plugins/disable-comments-rb/#pricing',
			'roboUrl'       => 'https://robosoft.co/?pk_campaign=wp_dc',
			'aiAssistant'  => isset( $this->options['ai_assistant'] ) ? $this->options['ai_assistant'] : array( 'enabled' => false, 'auto_publish' => false, 'rules' => array() ),
			'networkActive'   => $this->networkactive,
			'isNetworkAdmin'  => $is_net_admin_screen,
			'enforced'        => $this->enforced,
			'sitewideEnforce' => $this->networkactive ? (bool) get_site_option( 'rb_disable_comments_sitewide_enforce', false ) : false,
			'strings'    => array(
				'title'            => __( 'Disable Comments RB', 'disable-comments-rb' ),
				'intro'            => __( 'Choose where comments should be turned off on your site.', 'disable-comments-rb' ),
				'modeAll'          => __( 'Disable everywhere', 'disable-comments-rb' ),
				'modeSelected'     => __( 'Disable for selected content types', 'disable-comments-rb' ),
				'modeOff'          => __( 'Comments enabled (do nothing)', 'disable-comments-rb' ),
				'save'             => __( 'Save changes', 'disable-comments-rb' ),
				'saved'            => __( 'Settings saved.', 'disable-comments-rb' ),
				'error'            => __( 'Could not save settings. Please try again.', 'disable-comments-rb' ),
				'tabDisable'       => __( 'Disable', 'disable-comments-rb' ),
				'tabStatistics'    => __( 'Statistics', 'disable-comments-rb' ),
				'newCommentsLegend'=> __( 'New comments', 'disable-comments-rb' ),
				'blockNewLabel'    => __( 'Block visitors from posting new comments', 'disable-comments-rb' ),
				'exceptLegend'     => __( 'Exceptions', 'disable-comments-rb' ),
				'exceptIntro'      => __( 'These roles can always comment, even where comments are otherwise blocked above (useful for editors/admins leaving internal notes).', 'disable-comments-rb' ),
				'existingLegend'   => __( 'Comments already on the site', 'disable-comments-rb' ),
				'visAll'           => __( 'Keep showing them', 'disable-comments-rb' ),
				'visNone'          => __( 'Hide all of them', 'disable-comments-rb' ),
				'visDateRange'     => __( 'Hide by date', 'disable-comments-rb' ),
				'ruleBetween'      => __( 'Keep comments between two dates, hide the rest', 'disable-comments-rb' ),
				'ruleBefore'       => __( 'Hide everything before a date', 'disable-comments-rb' ),
				'dateFromLabel'    => __( 'From', 'disable-comments-rb' ),
				'dateToLabel'      => __( 'To', 'disable-comments-rb' ),
				'dateBeforeLabel'  => __( 'Before', 'disable-comments-rb' ),
				'delRuleAfter'     => __( 'Only comments after a date', 'disable-comments-rb' ),
				'delRuleBefore'    => __( 'Only comments before a date', 'disable-comments-rb' ),
				'delRuleBetween'   => __( 'Only comments between two dates', 'disable-comments-rb' ),
				'dateAfterLabel'   => __( 'After', 'disable-comments-rb' ),
				'statsLoading'     => __( 'Loading statistics…', 'disable-comments-rb' ),
				'statsError'       => __( 'Could not load statistics.', 'disable-comments-rb' ),
				'statsActive'      => __( 'Active', 'disable-comments-rb' ),
				'statsPending'     => __( 'Pending', 'disable-comments-rb' ),
				'statsSpam'        => __( 'Spam', 'disable-comments-rb' ),
				'statsTrash'       => __( 'Trash', 'disable-comments-rb' ),
				'statsTotal'       => __( 'Total', 'disable-comments-rb' ),
				'statsByType'      => __( 'By content type', 'disable-comments-rb' ),
				'tabDelete'        => __( 'Delete', 'disable-comments-rb' ),
				'delIntro'         => __( 'Permanently remove or trash comments matching the filters below.', 'disable-comments-rb' ),
				'delTypesLegend'   => __( 'Content types', 'disable-comments-rb' ),
				'delStatusLegend'  => __( 'Comment status', 'disable-comments-rb' ),
				'delApprove'       => __( 'Approved', 'disable-comments-rb' ),
				'delHold'          => __( 'Pending', 'disable-comments-rb' ),
				'delSpam'          => __( 'Spam', 'disable-comments-rb' ),
				'delTrash'         => __( 'Trash', 'disable-comments-rb' ),
				'delDateLegend'    => __( 'Comment date', 'disable-comments-rb' ),
				'delDateAny'       => __( 'Any date', 'disable-comments-rb' ),
				'delTypeLegend'    => __( 'Comment type', 'disable-comments-rb' ),
				'delTypeAll'       => __( 'All (comments + reviews)', 'disable-comments-rb' ),
				'delTypeComments'  => __( 'Comments only', 'disable-comments-rb' ),
				'delTypeReviews'   => __( 'Product reviews only', 'disable-comments-rb' ),
				'delPermanent'     => __( 'Delete permanently (skip Trash — cannot be undone)', 'disable-comments-rb' ),
				'delCount'         => __( 'Count matching comments', 'disable-comments-rb' ),
				'delCounting'      => __( 'Counting…', 'disable-comments-rb' ),
				/* translators: %d is replaced client-side (JS) with the number of matching comments. */
				'delCountResult'   => __( '%d comment(s) match these filters.', 'disable-comments-rb' ),
				'delButton'        => __( 'Delete matching comments', 'disable-comments-rb' ),
				/* translators: %d is replaced client-side (JS) with the number of comments about to be deleted permanently. */
				'delConfirm'       => __( 'This will permanently delete %d comment(s). This cannot be undone. Continue?', 'disable-comments-rb' ),
				/* translators: %d is replaced client-side (JS) with the number of comments about to be moved to Trash. */
				'delTrashConfirm'  => __( 'Move %d comment(s) to Trash?', 'disable-comments-rb' ),
				'delDeleting'      => __( 'Deleting…', 'disable-comments-rb' ),
				/* translators: %d is replaced client-side (JS) with the number of comments that were removed. */
				'delDone'          => __( '%d comment(s) removed.', 'disable-comments-rb' ),
				'delError'         => __( 'Choose at least one content type and one status.', 'disable-comments-rb' ),
				'statsDeleted'     => __( 'Deleted', 'disable-comments-rb' ),
				'statsOverview'    => __( 'Overview', 'disable-comments-rb' ),
				'tabGeneral'       => __( 'General Settings', 'disable-comments-rb' ),
				'tabAI'            => __( 'AI Assistant', 'disable-comments-rb' ),
				'aiBadge'          => __( 'New', 'disable-comments-rb' ),
				'genMenuLegend'    => __( 'Where this plugin lives in wp-admin', 'disable-comments-rb' ),
				'genMenuSub'       => __( 'Under Settings (default)', 'disable-comments-rb' ),
				'genMenuTop'       => __( 'As its own top-level menu item', 'disable-comments-rb' ),
				'genSave'          => __( 'Save', 'disable-comments-rb' ),
				'genSaved'         => __( 'Saved. Reload the page to see the menu change.', 'disable-comments-rb' ),
				'genApiLegend'     => __( 'Comment submission via API', 'disable-comments-rb' ),
				'genXmlrpc'        => __( 'Block comments submitted through XML-RPC', 'disable-comments-rb' ),
				'genRest'          => __( 'Block comments submitted through the REST API', 'disable-comments-rb' ),
				'genExportLegend'  => __( 'Backup & transfer', 'disable-comments-rb' ),
				'genExportBtn'     => __( 'Export settings', 'disable-comments-rb' ),
				'genImportBtn'     => __( 'Import settings', 'disable-comments-rb' ),
				'genImportChoose'  => __( 'Choose a .json file…', 'disable-comments-rb' ),
				'genImportDone'    => __( 'Settings imported. Reload the page to see them applied.', 'disable-comments-rb' ),
				'genImportError'   => __( 'That file doesn\'t look like a Disable Comments RB export.', 'disable-comments-rb' ),
				'genCliLegend'     => __( 'WP-CLI', 'disable-comments-rb' ),
				'genCliLabel'      => __( 'Enable WP-CLI commands for this plugin', 'disable-comments-rb' ),
				'genCliIntro'      => __( 'Run these from a terminal with WP-CLI installed, in your WordPress root:', 'disable-comments-rb' ),
				'genCliOff'        => __( 'WP-CLI commands are currently disabled — turn on the checkbox above and save to enable them.', 'disable-comments-rb' ),
				'genCliPro'        => __( 'WP-CLI commands are part of PRO. Unlock PRO to run status / set / stats / delete from the terminal.', 'disable-comments-rb' ),
				'genDashLegend'    => __( 'Dashboard widget', 'disable-comments-rb' ),
				'genDashLabel'     => __( 'Show comment statistics widget on the WordPress Dashboard', 'disable-comments-rb' ),
				'genCommentsBtnLegend' => __( 'Comments page shortcut', 'disable-comments-rb' ),
				'genCommentsBtnLabel'  => __( 'Show a settings button on the native Comments page', 'disable-comments-rb' ),
				'genAdminBarLegend'    => __( 'Toolbar shortcut', 'disable-comments-rb' ),
				'genAdminBarLabel'     => __( 'Show a quick-access menu in the admin toolbar', 'disable-comments-rb' ),
				'statsIntroText'   => __( '"Total" is Active + Pending comments (the ones a visitor could ever see or that are awaiting approval) — Spam and Trash are tracked separately since neither counts as a real comment yet.', 'disable-comments-rb' ),
				'statsTableIntro'  => __( 'Each row is a content type; each column is a comment status on that type. These numbers come straight from your comments table — nothing here is affected by the Disable tab\'s hide/show settings.', 'disable-comments-rb' ),
				'aiIntro'          => __( 'Automatically reply to new comments that mention specific keywords — e.g. a question about price or availability gets an instant templated answer instead of waiting on manual moderation.', 'disable-comments-rb' ),
				'aiEnable'         => __( 'Enable the AI Assistant', 'disable-comments-rb' ),
				'aiAutoPublish'    => __( 'Publish replies immediately (otherwise they wait in Pending for you to approve)', 'disable-comments-rb' ),
				'aiRulesLegend'    => __( 'Reply rules', 'disable-comments-rb' ),
				'aiKeywords'       => __( 'Keywords (comma-separated)', 'disable-comments-rb' ),
				'aiTone'           => __( 'Tone', 'disable-comments-rb' ),
				'aiToneFriendly'   => __( 'Friendly', 'disable-comments-rb' ),
				'aiToneProfessional' => __( 'Professional', 'disable-comments-rb' ),
				'aiToneCasual'     => __( 'Casual', 'disable-comments-rb' ),
				'aiTemplate'       => __( 'Reply template — use {name} and {link} as placeholders', 'disable-comments-rb' ),
				'aiLink'           => __( 'Link to insert (optional)', 'disable-comments-rb' ),
				'aiLinkLabel'      => __( 'Link label (optional)', 'disable-comments-rb' ),
				'aiAddRule'        => __( 'Add another rule', 'disable-comments-rb' ),
				'aiRemoveRule'     => __( 'Remove', 'disable-comments-rb' ),
				'aiSave'           => __( 'Save AI settings', 'disable-comments-rb' ),
				'aiPresetLabel'    => __( 'Start from a preset:', 'disable-comments-rb' ),
				'aiPresetAdd'      => __( '+ Add', 'disable-comments-rb' ),
				'aiScopeLabel'     => __( 'Applies to', 'disable-comments-rb' ),
				'aiScopeAny'       => __( 'Comments & reviews', 'disable-comments-rb' ),
				'aiScopeReviews'   => __( 'Product reviews only', 'disable-comments-rb' ),
				'aiScopeComments'  => __( 'Regular comments only', 'disable-comments-rb' ),
				'aiRatingLabel'    => __( 'Only trigger for star rating ≤ (0 = ignore rating)', 'disable-comments-rb' ),
				'statsReviews'     => __( 'Product reviews', 'disable-comments-rb' ),
				'netEnforceLegend' => __( 'Network enforcement', 'disable-comments-rb' ),
				'netEnforceLabel'  => __( 'Force these Disable settings on every site in the network (site admins won\'t be able to override them)', 'disable-comments-rb' ),
				'netSave'          => __( 'Save network settings', 'disable-comments-rb' ),
				'netIntro'         => __( 'These settings become the network default. Turn on enforcement below to also lock them for every site.', 'disable-comments-rb' ),
				'lockedTitle'      => __( 'Managed by your network administrator', 'disable-comments-rb' ),
				'lockedBody'       => __( 'The site network has enforced these comment settings for every site. You can still use the other tabs (Delete, AI Assistant, General) — those stay per-site.', 'disable-comments-rb' ),
				'proBadge'       => __( 'PRO', 'disable-comments-rb' ),
				'proLockedTitle' => __( 'This is a PRO feature', 'disable-comments-rb' ),
				'proBuyBtn'      => __( 'Unlock PRO', 'disable-comments-rb' ),
				'proCardTitle'   => __( 'Disable Comments RB — PRO', 'disable-comments-rb' ),
				'proCardIntro'   => __( 'Unlock the advanced tools below with a one-time or annual license.', 'disable-comments-rb' ),
				'proFeatures'    => array(
					__( 'AI Assistant — automatic replies to comments that mention your keywords', 'disable-comments-rb' ),
					__( 'Ready-made reply presets: Sales, Support, Availability, Complaints', 'disable-comments-rb' ),
					__( 'Replies triggered by a WooCommerce star rating — catch negative reviews', 'disable-comments-rb' ),
					__( 'Replies published at once, or held as Pending for your approval', 'disable-comments-rb' ),
					__( 'WooCommerce product review management', 'disable-comments-rb' ),
					__( 'Delete product reviews separately from regular comments', 'disable-comments-rb' ),
					__( 'Hide existing comments by date range — between, before or after a date', 'disable-comments-rb' ),
					__( 'Delete by comment type + date range', 'disable-comments-rb' ),
					__( 'Role-based exceptions — staff keep commenting where visitors cannot', 'disable-comments-rb' ),
					__( 'Multisite: enforce the settings network-wide from Network Admin', 'disable-comments-rb' ),
					__( 'Export/import settings as JSON', 'disable-comments-rb' ),
					__( 'WP-CLI: status, set, stats and delete commands for scripts', 'disable-comments-rb' ),
					__( 'Full Statistics tab — live counts by status and content type', 'disable-comments-rb' ),
					__( 'Product reviews shown separately in Statistics', 'disable-comments-rb' ),
					__( 'A running total of permanently deleted comments', 'disable-comments-rb' ),
					__( 'Smart modes: flexible rules for hiding and deleting', 'disable-comments-rb' ),
				),
				'proBannerIntro' => __( 'Unlock advanced tools with a one-time or annual license.', 'disable-comments-rb' ),
				'proShow'        => __( 'What\'s included', 'disable-comments-rb' ),
				'proHide'        => __( 'Hide features', 'disable-comments-rb' ),
				'roboShow'       => __( 'Our services', 'disable-comments-rb' ),
				'roboHide'       => __( 'Hide services', 'disable-comments-rb' ),
				'roboTitle'      => __( 'Need more done with WordPress than this plugin covers?', 'disable-comments-rb' ),
				'roboChip1'      => __( 'Custom development', 'disable-comments-rb' ),
				'roboChip2'      => __( 'Security audits', 'disable-comments-rb' ),
				'roboChip3'      => __( 'Hacked site recovery', 'disable-comments-rb' ),
				'roboChip4'      => __( 'Performance optimization', 'disable-comments-rb' ),
				'roboChip5'      => __( 'Plugin & web app development', 'disable-comments-rb' ),
				'roboBtn'        => __( 'Talk to RoboSoft', 'disable-comments-rb' ),
			),
			'help' => array(
				'mode_all'      => __( 'Every public content type — posts, pages, and any custom post type that supports comments — gets comments turned off in one go. Good for sites that never use comments at all.', 'disable-comments-rb' ),
				'mode_selected' => __( 'Pick exactly which content types lose comments. Everything not checked keeps working as before — useful when, say, Posts should stay open for discussion but Pages shouldn\'t.', 'disable-comments-rb' ),
				'mode_off'      => __( 'This plugin does nothing: comments behave exactly as WordPress\'s own Discussion settings say. Use this to temporarily pause the plugin\'s effect without losing your other settings below.', 'disable-comments-rb' ),
				'block_new'     => __( 'Controls the comment FORM only — whether visitors can submit a new comment. It\'s independent from the "Comments already on the site" section: you can block new comments while still showing old ones, or the reverse.', 'disable-comments-rb' ),
				'vis_all'       => __( 'Comments already posted stay visible to readers, even on a content type where new comments are blocked above.', 'disable-comments-rb' ),
				'vis_none'      => __( 'Every existing comment is hidden from the front end (it stays in the database — nothing is deleted here). Combine with "Block visitors from posting new comments" above for a fully silent page.', 'disable-comments-rb' ),
				'vis_date_range'=> __( 'Only comments inside the date window you set are shown; everything else is hidden without being deleted. Handy for closing discussion on an old announcement while keeping recent replies visible.', 'disable-comments-rb' ),
				'rule_between'  => __( 'Set a "From" and/or "To" date. Comments posted inside that window stay visible; comments outside it are hidden. Leave one side empty to only bound the other.', 'disable-comments-rb' ),
				'rule_before'   => __( 'Everything posted before this date is hidden; anything from this date onward stays visible.', 'disable-comments-rb' ),
				'del_types'     => __( 'Which content types to search when counting or deleting — same list as the Disable tab.', 'disable-comments-rb' ),
				'del_status'    => __( 'Which comment statuses to include. Tick "Trash" here to finally clear out comments you\'ve already trashed.', 'disable-comments-rb' ),
				'del_permanent' => __( 'Off: matches are moved to Trash (WordPress keeps them ~30 days and you can restore them from Comments → Trash). On: matches are deleted immediately with no recovery — use Count first to check the number.', 'disable-comments-rb' ),
				'del_date'      => __( 'Optional — narrows Count/Delete to comments posted in a date window: between two dates, before a date, or after a date. Picking a date automatically selects its option. The date you pick itself is excluded from "before" and "after". Leave on "Any date" to match by content type + status alone.', 'disable-comments-rb' ),
				'del_type'      => __( 'WooCommerce stores product reviews as a distinct comment type. Use this to clear out review spam without touching your regular blog comments, or vice versa.', 'disable-comments-rb' ),
				'ai_scope'      => __( 'Restricts this rule to WooCommerce product reviews, to regular comments, or leaves it open to both. Reviews only makes sense with WooCommerce active.', 'disable-comments-rb' ),
				'ai_rating'     => __( 'Set this above 0 to also require a low star rating — e.g. 2 means the rule only fires on 1- or 2-star reviews. You can combine this with keywords, or leave keywords empty to catch every low-rated review regardless of wording.', 'disable-comments-rb' ),
				'gen_menu'      => __( 'Purely cosmetic — doesn\'t change any comment behavior. "Top-level" gives the plugin its own sidebar icon instead of living under Settings; handy if you\'ll be in here often.', 'disable-comments-rb' ),
				'gen_xmlrpc'    => __( 'Closes the classic XML-RPC endpoint some spam bots still use to post comments directly, bypassing the normal comment form entirely. Doesn\'t affect XML-RPC for anything else (like the mobile app).', 'disable-comments-rb' ),
				'gen_rest'      => __( 'Blocks POST requests to the REST API\'s comments endpoint (/wp/v2/comments). Reading comments through the API still works — this only stops new ones being created that way.', 'disable-comments-rb' ),
				'ai_enable'     => __( 'Master switch for the whole feature. Turning this off stops all auto-replies immediately without losing your saved rules.', 'disable-comments-rb' ),
				'ai_auto_publish' => __( 'Recommended off at first: replies land in Pending so you can check the wording before real visitors see it. Turn on once you trust the rules.', 'disable-comments-rb' ),
				'ai_keywords'   => __( 'Case-insensitive, matched as substrings — "price" also matches "pricing". A comment only needs to contain ONE of these words to trigger this rule.', 'disable-comments-rb' ),
				'ai_template'   => __( '{name} becomes the commenter\'s name, {link} becomes the URL from the field below. Rules are checked top to bottom and only the first match replies — order matters.', 'disable-comments-rb' ),
				'net_enforce'   => __( 'When on, every site in the network uses THESE Disable-tab settings and can\'t change them locally — their Disable tab shows a locked notice instead. Delete, AI Assistant, and General stay per-site either way.', 'disable-comments-rb' ),
				'exceptions'    => __( 'An exemption only affects NEW comments (the block-new rule) — it doesn\'t change what\'s shown from "Comments already on the site" above, and it has no effect on content types that aren\'t currently disabled.', 'disable-comments-rb' ),
				'gen_export'    => __( 'Downloads a .json file with everything on the Disable, General, and AI Assistant tabs — handy for cloning settings to another site or keeping a backup before a big change. Delete-tab actions and statistics are never included.', 'disable-comments-rb' ),
				'gen_cli'       => __( 'Only affects whether the `wp disable-comments-rb` commands exist — has nothing to do with whether WP-CLI itself is installed on the server. Off by default costs nothing to leave on; it\'s here mainly for hosts that lock down which plugins can register CLI commands.', 'disable-comments-rb' ),
				'gen_dashboard' => __( 'Adds a small stats + release-notes card to your WordPress Dashboard. Closing it with the × also turns this checkbox off — re-check it and save here to bring the widget back.', 'disable-comments-rb' ),
				'gen_comments_btn' => __( 'Adds a "Disable Comments RB Settings" button on the standard Comments page (next to the comment-type filter), so moderators don\'t have to hunt through Settings to find this plugin.', 'disable-comments-rb' ),
				'gen_admin_bar'    => __( 'Adds a "Disable Comments RB" item to the black admin toolbar at the top of every screen (front-end included), with a dropdown that jumps straight to any tab.', 'disable-comments-rb' ),
			),
		) );
	}

	/**
	 * AJAX save handler for the settings screen. Mirrors the validation
	 * that options.php used to do inline (capability check, nonce,
	 * intersect against real post types) so the trust boundary doesn't
	 * change just because the transport did.
	 */
	public function ajax_save_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Not allowed.', 'disable-comments-rb' ) ), 403 );
		}

		check_ajax_referer( 'rb-disable-comments-ajax', 'nonce' );

		$is_network_save = $this->is_network_admin_ajax_context() && current_user_can( 'manage_network_options' );

		// A site admin can't bypass network enforcement by calling the
		// per-site AJAX action directly with a forged referer — the lock
		// is checked here, not just hidden in the UI.
		if ( $this->enforced && ! $is_network_save ) {
			wp_send_json_error( array( 'message' => __( 'These settings are enforced by the network administrator.', 'disable-comments-rb' ) ), 403 );
		}

		$posted_types = array();
		if ( isset( $_POST['post_types'] ) && is_array( $_POST['post_types'] ) ) {
			$posted_types = array_map( 'sanitize_key', wp_unslash( $_POST['post_types'] ) );
		}
		$posted_roles = array();
		if ( isset( $_POST['exempt_roles'] ) && is_array( $_POST['exempt_roles'] ) ) {
			$posted_roles = array_map( 'sanitize_key', wp_unslash( $_POST['exempt_roles'] ) );
		}

		$args = array(
			'mode'                => isset( $_POST['mode'] ) ? sanitize_key( wp_unslash( $_POST['mode'] ) ) : 'off',
			'post_types'          => $posted_types,
			'block_new'           => ! empty( $_POST['block_new'] ),
			'existing_visibility' => isset( $_POST['existing_visibility'] ) ? sanitize_key( wp_unslash( $_POST['existing_visibility'] ) ) : 'none',
			'date_rule'           => isset( $_POST['date_rule'] ) ? sanitize_key( wp_unslash( $_POST['date_rule'] ) ) : 'between',
			'date_from'           => isset( $_POST['date_from'] ) ? sanitize_text_field( wp_unslash( $_POST['date_from'] ) ) : '',
			'date_to'             => isset( $_POST['date_to'] ) ? sanitize_text_field( wp_unslash( $_POST['date_to'] ) ) : '',
			'date_before'         => isset( $_POST['date_before'] ) ? sanitize_text_field( wp_unslash( $_POST['date_before'] ) ) : '',
			'exempt_roles'        => $posted_roles,
		);

		$result = $is_network_save
			? $this->with_network_options( function () use ( $args ) { return $this->apply_disable_settings( $args ); } )
			: $this->apply_disable_settings( $args );

		wp_send_json_success( $result );
	}

	/**
	 * Everything the Disable tab's Save button does, minus the HTTP
	 * plumbing — shared with the WP-CLI `set` command so the two stay
	 * in sync by construction instead of by remembering to update both.
	 * $args uses the same keys/shape as the AJAX POST body above.
	 */
	public function apply_disable_settings( $args ) {
		$defaults = array(
			'mode' => 'off', 'post_types' => array(), 'block_new' => true,
			'existing_visibility' => 'none', 'date_rule' => 'between',
			'date_from' => '', 'date_to' => '', 'date_before' => '',
			'exempt_roles' => null, // null = leave whatever's already stored untouched
		);
		$args = array_merge( $defaults, $args );

		$valid_types  = array_keys( $this->get_selectable_post_types() );
		// array_filter: an import file is JSON someone may have edited, lists inside this list included
		$posted_types = array_values( array_unique( array_intersect( array_filter( (array) $args['post_types'], 'is_string' ), $valid_types ) ) );

		$mode = in_array( $args['mode'], array( 'all', 'selected', 'off' ), true ) ? $args['mode'] : 'off';

		if ( 'all' === $mode ) {
			$disabled_post_types = $valid_types;
			$this->options['remove_everywhere'] = true;
		} elseif ( 'selected' === $mode ) {
			$disabled_post_types = $posted_types;
			$this->options['remove_everywhere'] = false;
		} else {
			$disabled_post_types = array();
			$this->options['remove_everywhere'] = false;
		}
		$this->options['disabled_post_types'] = $disabled_post_types;
		$this->options['block_new'] = ! empty( $args['block_new'] );

		// Without the key nothing reads the role exceptions: not kept.
		if ( ! $this->is_pro() ) {
			$this->options['exempt_roles'] = array();
		}

		$this->options['existing_visibility'] = in_array( $args['existing_visibility'], $this->visibility_modes(), true ) ? $args['existing_visibility'] : 'none';

		// What the PRO key's features store from the same save.
		$this->options = (array) apply_filters( 'rb_disable_comments_disable_settings', $this->options, $args );

		$this->save_options();

		return array( 'mode' => $mode, 'selected' => $disabled_post_types );
	}

	/**
	 * Comment counts for the Statistics tab: totals by status, and a
	 * breakdown by post type (mirroring the post types the Disable tab
	 * lets you toggle). One query, grouped in PHP — cheap enough for a
	 * settings-screen read and avoids N queries for N post types.
	 * Public + side-effect-free so `wp disable-comments-rb stats` can
	 * call it directly instead of duplicating the aggregation.
	 */
	public function compute_stats() {
		global $wpdb;

		// Block-editor notes (comment type "note", WP 6.9+) are an editorial tool, not comments.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- no equivalent core API groups comments by post type + status + type in one call.
		$rows = $wpdb->get_results(
			"SELECT p.post_type AS post_type, c.comment_approved AS status, c.comment_type AS comment_type, COUNT(*) AS cnt
			 FROM {$wpdb->comments} c
			 INNER JOIN {$wpdb->posts} p ON p.ID = c.comment_post_ID
			 WHERE c.comment_type <> 'note'
			 GROUP BY p.post_type, c.comment_approved, c.comment_type"
		);

		$labels = array();
		foreach ( get_post_types( array(), 'objects' ) as $slug => $obj ) {
			$labels[ $slug ] = $obj->labels->name;
		}

		$by_type = array();
		$totals  = array( 'active' => 0, 'pending' => 0, 'spam' => 0, 'trash' => 0 );
		$reviews = array( 'active' => 0, 'pending' => 0, 'spam' => 0, 'trash' => 0 );

		foreach ( $rows as $row ) {
			$type = $row->post_type;
			if ( ! isset( $by_type[ $type ] ) ) {
				$by_type[ $type ] = array(
					'label'   => isset( $labels[ $type ] ) ? $labels[ $type ] : $type,
					'active'  => 0,
					'pending' => 0,
					'spam'    => 0,
					'trash'   => 0,
				);
			}

			$bucket = 'active';
			if ( 'spam' === $row->status ) {
				$bucket = 'spam';
			} elseif ( 'trash' === $row->status ) {
				$bucket = 'trash';
			} elseif ( '0' === $row->status ) {
				$bucket = 'pending';
			}

			$by_type[ $type ][ $bucket ] += (int) $row->cnt;
			$totals[ $bucket ]           += (int) $row->cnt;

			if ( 'review' === $row->comment_type ) {
				$reviews[ $bucket ] += (int) $row->cnt;
			}
		}

		return array(
			'totals'  => $totals,
			'byType'  => array_values( $by_type ),
			'reviews' => $reviews,
			'wooActive' => class_exists( 'WooCommerce' ),
			'isPro' => $this->is_pro(),
			'deletedPermanently' => isset( $this->options['total_deleted_count'] ) ? (int) $this->options['total_deleted_count'] : 0,
		);
	}

	public function ajax_get_stats() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Not allowed.', 'disable-comments-rb' ) ), 403 );
		}
		check_ajax_referer( 'rb-disable-comments-ajax', 'nonce' );

		wp_send_json_success( $this->compute_stats() );
	}

	/**
	 * Narrows raw post-type/status input down to real values. Returns
	 * [ post_types, statuses ] on success or a WP_Error on an empty
	 * result — side-effect-free (no wp_send_json here) so both the AJAX
	 * handlers and the CLI command can react to the failure their own way.
	 */
	public function validate_delete_filters( $post_types_raw, $statuses_raw ) {
		$valid_types    = array_keys( $this->get_selectable_post_types() );
		$valid_statuses = array( 'approve', 'hold', 'spam', 'trash' );

		$post_types = array_values( array_intersect( array_map( 'sanitize_key', (array) $post_types_raw ), $valid_types ) );
		$statuses   = array_values( array_intersect( array_map( 'sanitize_key', (array) $statuses_raw ), $valid_statuses ) );

		if ( empty( $post_types ) || empty( $statuses ) ) {
			return new WP_Error( 'rb_disable_comments_empty_filter', __( 'Choose at least one content type and one status.', 'disable-comments-rb' ) );
		}

		return array( $post_types, $statuses );
	}

	private function get_matching_comment_ids( $post_types, $statuses, $date_query = array(), $comment_type = '' ) {
		$ids = array();
		foreach ( $statuses as $status ) {
			$args = array(
				'post_type' => $post_types,
				'status'    => $status,
				'fields'    => 'ids',
				'number'    => 0,
			);
			if ( ! empty( $date_query ) ) {
				$args['date_query'] = $date_query;
			}
			if ( '' !== $comment_type ) {
				$args['type'] = $comment_type; // 'comment' or 'review' (WooCommerce)
			}
			$query = new WP_Comment_Query( $args );
			$ids = array_merge( $ids, (array) $query->comments );
		}
		return array_unique( array_map( 'intval', $ids ) );
	}

	/**
	 * Deletes (or trashes) every comment matching $post_types + $statuses
	 * (and, optionally, $date_query) and returns the count removed.
	 * Shared by the AJAX delete handler and `wp disable-comments-rb delete`.
	 */
	public function delete_matching_comments( $post_types, $statuses, $permanent, $date_query = array(), $comment_type = '' ) {
		$ids = $this->get_matching_comment_ids( $post_types, $statuses, $date_query, $comment_type );

		$deleted = 0;
		foreach ( $ids as $id ) {
			if ( wp_delete_comment( $id, $permanent ) ) {
				$deleted++;
			}
		}

		if ( $permanent && $deleted ) {
			$running = isset( $this->options['total_deleted_count'] ) ? (int) $this->options['total_deleted_count'] : 0;
			$this->options['total_deleted_count'] = $running + $deleted;
			$this->save_options();
		}

		return $deleted;
	}

	/** The PRO key's filters for count and delete; the request is verified before this runs. */
	private function delete_filters() {
		$filters = (array) apply_filters( 'rb_disable_comments_delete_filters', array( 'date_query' => array(), 'comment_type' => '' ) );
		return array(
			isset( $filters['date_query'] ) ? (array) $filters['date_query'] : array(),
			isset( $filters['comment_type'] ) ? (string) $filters['comment_type'] : '',
		);
	}

	public function ajax_count_comments() {
		if ( ! current_user_can( 'moderate_comments' ) ) {
			wp_send_json_error( array( 'message' => __( 'Not allowed.', 'disable-comments-rb' ) ), 403 );
		}
		check_ajax_referer( 'rb-disable-comments-ajax', 'nonce' );

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized two lines down, inside validate_delete_filters() (sanitize_key + intersect against a whitelist).
		$raw_post_types = isset( $_POST['post_types'] ) ? wp_unslash( $_POST['post_types'] ) : array();
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- see note above.
		$raw_statuses   = isset( $_POST['statuses'] ) ? wp_unslash( $_POST['statuses'] ) : array();
		$filters = $this->validate_delete_filters( $raw_post_types, $raw_statuses );
		if ( is_wp_error( $filters ) ) {
			wp_send_json_error( array( 'message' => $filters->get_error_message() ), 400 );
		}
		list( $post_types, $statuses ) = $filters;
		list( $date_query, $comment_type ) = $this->delete_filters();

		wp_send_json_success( array( 'count' => count( $this->get_matching_comment_ids( $post_types, $statuses, $date_query, $comment_type ) ) ) );
	}

	/**
	 * $_POST['permanent'] mirrors wp_delete_comment()'s $force_delete:
	 * unset/empty moves matches to Trash (reversible), checked bypasses
	 * Trash entirely — irreversible, so the confirm step lives in the UI
	 * before this ever fires.
	 */
	public function ajax_delete_comments() {
		if ( ! current_user_can( 'moderate_comments' ) ) {
			wp_send_json_error( array( 'message' => __( 'Not allowed.', 'disable-comments-rb' ) ), 403 );
		}
		check_ajax_referer( 'rb-disable-comments-ajax', 'nonce' );

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized two lines down, inside validate_delete_filters() (sanitize_key + intersect against a whitelist).
		$raw_post_types = isset( $_POST['post_types'] ) ? wp_unslash( $_POST['post_types'] ) : array();
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- see note above.
		$raw_statuses   = isset( $_POST['statuses'] ) ? wp_unslash( $_POST['statuses'] ) : array();
		$filters = $this->validate_delete_filters( $raw_post_types, $raw_statuses );
		if ( is_wp_error( $filters ) ) {
			wp_send_json_error( array( 'message' => $filters->get_error_message() ), 400 );
		}
		list( $post_types, $statuses ) = $filters;
		$permanent = ! empty( $_POST['permanent'] );
		list( $date_query, $comment_type ) = $this->delete_filters();

		$deleted = $this->delete_matching_comments( $post_types, $statuses, $permanent, $date_query, $comment_type );

		wp_send_json_success( array(
			'deleted'   => $deleted,
			'permanent' => $permanent,
		) );
	}

	/**
	 * General tab: currently just where the settings screen lives in
	 * wp-admin. Kept as its own handler/nonce-scope so it doesn't get
	 * tangled up with the Disable tab's save button.
	 */
	public function ajax_save_general() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Not allowed.', 'disable-comments-rb' ) ), 403 );
		}
		check_ajax_referer( 'rb-disable-comments-ajax', 'nonce' );

		$location = isset( $_POST['menu_location'] ) ? sanitize_key( wp_unslash( $_POST['menu_location'] ) ) : 'submenu';
		$this->options['menu_location'] = ( 'top' === $location ) ? 'top' : 'submenu';
		$this->options['remove_xmlrpc_comments'] = ! empty( $_POST['remove_xmlrpc_comments'] );
		$this->options['remove_rest_comments']   = ! empty( $_POST['remove_rest_comments'] );
		$this->options['show_dashboard_widget'] = ! empty( $_POST['show_dashboard_widget'] );
		$this->options['show_comments_page_button'] = ! empty( $_POST['show_comments_page_button'] );
		$this->options['show_admin_bar_menu'] = ! empty( $_POST['show_admin_bar_menu'] );
		// What the PRO key adds to this tab.
		$this->options = (array) apply_filters( 'rb_disable_comments_general_settings', $this->options );
		$this->save_options();

		wp_send_json_success( array(
			'menuLocation' => $this->options['menu_location'],
			'settingsUrl'  => $this->settings_page_url(),
		) );
	}

	/**
	 * Abilities API (WordPress core, still rolling out as of this
	 * writing) — lets an AI agent with the right capability query and
	 * change this plugin's configuration the same way a person would
	 * through the settings screen. Both callbacks are no-ops unless the
	 * relevant wp_register_* functions exist.
	 */
	public function register_ability_categories() {
		if ( function_exists( 'wp_register_ability_category' ) ) {
			wp_register_ability_category( 'disable-comments-rb', array(
				'label' => __( 'Disable Comments RB', 'disable-comments-rb' ),
			) );
		}
	}

	public function register_abilities() {
		if ( $this->abilities_registered || ! function_exists( 'wp_register_ability' ) ) {
			return;
		}
		$this->abilities_registered = true;

		wp_register_ability( 'disable-comments-rb/get-status', array(
			'label'               => __( 'Get comment-disabling status', 'disable-comments-rb' ),
			'description'         => __( 'Returns this site\'s current Disable Comments RB configuration.', 'disable-comments-rb' ),
			'category'            => 'disable-comments-rb',
			'input_schema'        => array( 'type' => 'object', 'properties' => new stdClass() ),
			'output_schema'       => array( 'type' => 'object' ),
			'execute_callback'    => array( $this, 'ability_get_status' ),
			'permission_callback' => function () { return current_user_can( 'manage_options' ); },
		) );

		wp_register_ability( 'disable-comments-rb/set-mode', array(
			'label'         => __( 'Set comment-disabling mode', 'disable-comments-rb' ),
			'description'   => __( 'Sets where comments are disabled: everywhere, selected content types, or off.', 'disable-comments-rb' ),
			'category'      => 'disable-comments-rb',
			'input_schema'  => array(
				'type'       => 'object',
				'properties' => array(
					'mode'       => array( 'type' => 'string', 'enum' => array( 'all', 'selected', 'off' ) ),
					'post_types' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
				),
				'required'   => array( 'mode' ),
			),
			'output_schema'       => array( 'type' => 'object' ),
			'execute_callback'    => array( $this, 'ability_set_mode' ),
			'permission_callback' => function () { return current_user_can( 'manage_options' ); },
		) );
	}

	/** get-status: the configuration the description promises, plus the comment totals. */
	public function ability_get_status() {
		$stats = $this->compute_stats();
		return array(
			'mode'                => ! empty( $this->options['remove_everywhere'] ) ? 'all' : ( $this->get_disabled_post_types() ? 'selected' : 'off' ),
			'post_types'          => $this->get_disabled_post_types(),
			'block_new'           => ! isset( $this->options['block_new'] ) || $this->options['block_new'],
			'existing_visibility' => isset( $this->options['existing_visibility'] ) ? $this->options['existing_visibility'] : 'none',
			'enforced_by_network' => $this->enforced,
			'totals'              => $stats['totals'],
		);
	}

	/**
	 * set-mode: changes only the mode and post types. Everything else on
	 * the Disable tab (block_new, visibility, dates, exceptions) is passed
	 * through as it is now — apply_disable_settings()' defaults would
	 * otherwise reset visibility to "none" and hide every existing comment.
	 * Refused while the network enforces the settings, like the AJAX save.
	 */
	public function ability_set_mode( $input ) {
		if ( $this->enforced ) {
			return new WP_Error( 'rb_disable_comments_enforced', __( 'These settings are enforced by the network administrator.', 'disable-comments-rb' ) );
		}

		$input = (array) $input;
		$mode  = isset( $input['mode'] ) ? (string) $input['mode'] : '';
		if ( ! in_array( $mode, array( 'all', 'selected', 'off' ), true ) ) {
			return new WP_Error( 'rb_disable_comments_bad_mode', __( 'Mode must be "all", "selected" or "off".', 'disable-comments-rb' ) );
		}

		$post_types = isset( $input['post_types'] ) ? array_map( 'sanitize_key', (array) $input['post_types'] ) : $this->get_disabled_post_types();
		if ( 'selected' === $mode && ! array_intersect( $post_types, array_keys( $this->get_selectable_post_types() ) ) ) {
			return new WP_Error( 'rb_disable_comments_no_types', __( 'Mode "selected" needs at least one content type that supports comments.', 'disable-comments-rb' ) );
		}

		$o = $this->options;
		return $this->apply_disable_settings( array(
			'mode'                => $mode,
			'post_types'          => $post_types,
			'block_new'           => ! isset( $o['block_new'] ) || $o['block_new'],
			'existing_visibility' => isset( $o['existing_visibility'] ) ? $o['existing_visibility'] : 'none',
			'date_rule'           => isset( $o['date_rule'] ) ? $o['date_rule'] : 'between',
			'date_from'           => isset( $o['date_from'] ) ? $o['date_from'] : '',
			'date_to'             => isset( $o['date_to'] ) ? $o['date_to'] : '',
			'date_before'         => isset( $o['date_before'] ) ? $o['date_before'] : '',
			'exempt_roles'        => null,
		) );
	}

}