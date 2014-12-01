<?php

/**
 * Main BuddyPress Admin Class.
 *
 * @package BuddyPress
 * @subpackage CoreAdministration
 */

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

if ( !class_exists( 'BP_Admin' ) ) :
/**
 * Load BuddyPress plugin admin area.
 *
 * @package BuddyPress
 * @subpackage CoreAdministration
 *
 * @since BuddyPress (1.6.0)
 */
class BP_Admin {

	/** Directory *************************************************************/

	/**
	 * Path to the BuddyPress admin directory.
	 *
	 * @var string $admin_dir
	 */
	public $admin_dir = '';

	/** URLs ******************************************************************/

	/**
	 * URL to the BuddyPress admin directory.
	 *
	 * @var string $admin_url
	 */
	public $admin_url = '';

	/**
	 * URL to the BuddyPress images directory.
	 *
	 * @var string $images_url
	 */
	public $images_url = '';

	/**
	 * URL to the BuddyPress admin CSS directory.
	 *
	 * @var string $css_url
	 */
	public $css_url = '';

	/**
	 * URL to the BuddyPress admin JS directory.
	 *
	 * @var string
	 */
	public $js_url = '';

	/** Other *****************************************************************/

	/**
	 * Notices used for user feedback, like saving settings.
	 *
	 * @var array()
	 */
	public $notices = array();

	/** Methods ***************************************************************/

	/**
	 * The main BuddyPress admin loader.
	 *
	 * @since BuddyPress (1.6.0)
	 *
	 * @uses BP_Admin::setup_globals() Setup the globals needed.
	 * @uses BP_Admin::includes() Include the required files.
	 * @uses BP_Admin::setup_actions() Setup the hooks and actions.
	 */
	public function __construct() {
		$this->setup_globals();
		$this->includes();
		$this->setup_actions();
	}

	/**
	 * Set admin-related globals.
	 *
	 * @access private
	 * @since BuddyPress (1.6.0)
	 */
	private function setup_globals() {
		$bp = buddypress();

		// Paths and URLs
		$this->admin_dir  = trailingslashit( $bp->plugin_dir  . 'bp-core/admin' ); // Admin path
		$this->admin_url  = trailingslashit( $bp->plugin_url  . 'bp-core/admin' ); // Admin url
		$this->images_url = trailingslashit( $this->admin_url . 'images'        ); // Admin images URL
		$this->css_url    = trailingslashit( $this->admin_url . 'css'           ); // Admin css URL
		$this->js_url     = trailingslashit( $this->admin_url . 'js'            ); // Admin css URL

		// Main settings page
		$this->settings_page = bp_core_do_network_admin() ? 'settings.php' : 'options-general.php';

		// Main capability
		$this->capability = bp_core_do_network_admin() ? 'manage_network_options' : 'manage_options';
	}

	/**
	 * Include required files.
	 *
	 * @since BuddyPress (1.6.0)
	 * @access private
	 */
	private function includes() {
		require( $this->admin_dir . 'bp-core-actions.php'    );
		require( $this->admin_dir . 'bp-core-settings.php'   );
		require( $this->admin_dir . 'bp-core-functions.php'  );
		require( $this->admin_dir . 'bp-core-components.php' );
		require( $this->admin_dir . 'bp-core-slugs.php'      );
		require( $this->admin_dir . 'bp-core-tools.php'      );
	}

	/**
	 * Set up the admin hooks, actions, and filters.
	 *
	 * @access private
	 * @since BuddyPress (1.6.0)
	 *
	 * @uses add_action() To add various actions.
	 * @uses add_filter() To add various filters.
	 */
	private function setup_actions() {

		/** General Actions ***************************************************/

		// Add some page specific output to the <head>
		add_action( 'bp_admin_head',            array( $this, 'admin_head'  ), 999 );

		// Add menu item to settings menu
		add_action( bp_core_admin_hook(),       array( $this, 'admin_menus' ), 5 );

		// Enqueue all admin JS and CSS
		add_action( 'bp_admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

		/** BuddyPress Actions ************************************************/

		// Load the BuddyPress metabox in the WP Nav Menu Admin UI
		add_action( 'load-nav-menus.php', 'bp_admin_wp_nav_menu_meta_box' );

		// Add settings
		add_action( 'bp_register_admin_settings', array( $this, 'register_admin_settings' ) );

		// Add a description of new BuddyPress tools in the available tools page
		add_action( 'tool_box', 'bp_core_admin_available_tools_intro' );
		add_action( 'bp_network_tool_box', 'bp_core_admin_available_tools_intro' );

		// On non-multisite, catch
		add_action( 'load-users.php', 'bp_core_admin_user_manage_spammers' );

		/** Filters ***********************************************************/

		// Add link to settings page
		add_filter( 'plugin_action_links',               array( $this, 'modify_plugin_action_links' ), 10, 2 );
		add_filter( 'network_admin_plugin_action_links', array( $this, 'modify_plugin_action_links' ), 10, 2 );

		// Add "Mark as Spam" row actions on users.php
		add_filter( 'ms_user_row_actions', 'bp_core_admin_user_row_actions', 10, 2 );
		add_filter( 'user_row_actions',    'bp_core_admin_user_row_actions', 10, 2 );
	}

	/**
	 * Add the navigational menu elements.
	 *
	 * @since BuddyPress (1.6)
	 *
	 * @uses add_management_page() To add the Recount page in Tools section.
	 * @uses add_options_page() To add the Forums settings page in Settings
	 *       section.
	 */
	public function admin_menus() {

		// Bail if user cannot moderate
		if ( ! bp_current_user_can( 'manage_options' ) )
			return;

		// About
		add_dashboard_page(
			_x( 'BuddyPress Dashboard', 'Dashboard page title', 'buddypress' ),
			_x( 'BuddyPress', 'Dashboard menu title', 'buddypress' ),
			'manage_options',
			'bp-about',
			array( $this, 'about_screen' )
		);

		$hooks = array();

		// Changed in BP 1.6 . See bp_core_admin_backpat_menu()
		$hooks[] = add_menu_page(
			__( 'BuddyPress', 'buddypress' ),
			__( 'BuddyPress', 'buddypress' ),
			$this->capability,
			'bp-general-settings',
			'bp_core_admin_backpat_menu',
			'div'
		);

		$hooks[] = add_submenu_page(
			'bp-general-settings',
			__( 'BuddyPress Help', 'buddypress' ),
			__( 'Help', 'buddypress' ),
			$this->capability,
			'bp-general-settings',
			'bp_core_admin_backpat_page'
		);

		// Add the option pages
		$hooks[] = add_submenu_page(
			$this->settings_page,
			__( 'BuddyPress Components', 'buddypress' ),
			__( 'BuddyPress', 'buddypress' ),
			$this->capability,
			'bp-components',
			'bp_core_admin_components_settings'
		);

		$hooks[] = add_submenu_page(
			$this->settings_page,
			__( 'BuddyPress Pages', 'buddypress' ),
			__( 'BuddyPress Pages', 'buddypress' ),
			$this->capability,
			'bp-page-settings',
			'bp_core_admin_slugs_settings'
		);

		$hooks[] = add_submenu_page(
			$this->settings_page,
			__( 'BuddyPress Settings', 'buddypress' ),
			__( 'BuddyPress Settings', 'buddypress' ),
			$this->capability,
			'bp-settings',
			'bp_core_admin_settings'
		);

		// For consistency with non-Multisite, we add a Tools menu in
		// the Network Admin as a home for our Tools panel
		if ( is_multisite() && bp_core_do_network_admin() ) {
			$tools_parent = 'network-tools';

			$hooks[] = add_menu_page(
				__( 'Tools', 'buddypress' ),
				__( 'Tools', 'buddypress' ),
				$this->capability,
				$tools_parent,
				'bp_core_tools_top_level_item',
				'',
				24 // just above Settings
			);

			$hooks[] = add_submenu_page(
				$tools_parent,
				__( 'Available Tools', 'buddypress' ),
				__( 'Available Tools', 'buddypress' ),
				$this->capability,
				'available-tools',
				'bp_core_admin_available_tools_page'
			);
		} else {
			$tools_parent = 'tools.php';
		}

		$hooks[] = add_submenu_page(
			$tools_parent,
			__( 'BuddyPress Tools', 'buddypress' ),
			__( 'BuddyPress', 'buddypress' ),
			$this->capability,
			'bp-tools',
			'bp_core_admin_tools'
		);

		// Fudge the highlighted subnav item when on a BuddyPress admin page
		foreach( $hooks as $hook ) {
			add_action( "admin_head-$hook", 'bp_core_modify_admin_menu_highlight' );
		}
	}

	/**
	 * Register the settings.
	 *
	 * @since BuddyPress (1.6.0)
	 *
	 * @uses add_settings_section() To add our own settings section.
	 * @uses add_settings_field() To add various settings fields.
	 * @uses register_setting() To register various settings.
	 */
	public function register_admin_settings() {

		/** Main Section ******************************************************/

		// Add the main section
		add_settings_section( 'bp_main',            __( 'Main Settings',    'buddypress' ), 'bp_admin_setting_callback_main_section',     'buddypress'            );

		// Hide toolbar for logged out users setting
		add_settings_field( 'hide-loggedout-adminbar',        __( 'Toolbar',        'buddypress' ), 'bp_admin_setting_callback_admin_bar',        'buddypress', 'bp_main' );
	 	register_setting  ( 'buddypress',           'hide-loggedout-adminbar',        'intval'                                                                              );

		// Only show 'switch to Toolbar' option if the user chose to retain the BuddyBar during the 1.6 upgrade
		if ( (bool) bp_get_option( '_bp_force_buddybar', false ) ) {
			add_settings_field( '_bp_force_buddybar', __( 'Toolbar', 'buddypress' ), 'bp_admin_setting_callback_force_buddybar', 'buddypress', 'bp_main' );
		 	register_setting( 'buddypress', '_bp_force_buddybar', 'bp_admin_sanitize_callback_force_buddybar' );
		}

		// Allow account deletion
		add_settings_field( 'bp-disable-account-deletion', __( 'Account Deletion', 'buddypress' ), 'bp_admin_setting_callback_account_deletion', 'buddypress', 'bp_main' );
	 	register_setting  ( 'buddypress',           'bp-disable-account-deletion', 'intval'                                                                              );

		/** XProfile Section **************************************************/

		if ( bp_is_active( 'xprofile' ) ) {

			// Add the main section
			add_settings_section( 'bp_xprofile', _x( 'Profile Settings', 'BuddyPress setting tab', 'buddypress' ), 'bp_admin_setting_callback_xprofile_section', 'buddypress' );

			$avatar_setting = 'bp_xprofile';

			// Profile sync setting
			add_settings_field( 'bp-disable-profile-sync',   __( 'Profile Syncing',  'buddypress' ), 'bp_admin_setting_callback_profile_sync',     'buddypress', 'bp_xprofile' );
			register_setting  ( 'buddypress',         'bp-disable-profile-sync',     'intval'                                                                                  );
		}

		/** Groups Section ****************************************************/

		if ( bp_is_active( 'groups' ) ) {

			// Add the main section
			add_settings_section( 'bp_groups',        __( 'Groups Settings',  'buddypress' ), 'bp_admin_setting_callback_groups_section',   'buddypress'              );

			if ( empty( $avatar_setting ) )
				$avatar_setting = 'bp_groups';

			// Allow subscriptions setting
			add_settings_field( 'bp_restrict_group_creation', __( 'Group Creation',   'buddypress' ), 'bp_admin_setting_callback_group_creation',   'buddypress', 'bp_groups' );
			register_setting  ( 'buddypress',         'bp_restrict_group_creation',   'intval'                                                                                );
		}

		/** Forums ************************************************************/

		if ( bp_is_active( 'forums' ) ) {

			// Add the main section
			add_settings_section( 'bp_forums',        __( 'Legacy Group Forums',       'buddypress' ), 'bp_admin_setting_callback_bbpress_section',       'buddypress'              );

			// Allow subscriptions setting
			add_settings_field( 'bb-config-location', __( 'bbPress Configuration', 'buddypress' ), 'bp_admin_setting_callback_bbpress_configuration', 'buddypress', 'bp_forums' );
			register_setting  ( 'buddypress',         'bb-config-location',        ''                                                                                           );
		}

		/** Activity Section **************************************************/

		if ( bp_is_active( 'activity' ) ) {

			// Add the main section
			add_settings_section( 'bp_activity',      __( 'Activity Settings', 'buddypress' ), 'bp_admin_setting_callback_activity_section', 'buddypress'                );

			// Activity commenting on blog and forum posts
			add_settings_field( 'bp-disable-blogforum-comments', __( 'Blog &amp; Forum Comments', 'buddypress' ), 'bp_admin_setting_callback_blogforum_comments', 'buddypress', 'bp_activity' );
			register_setting( 'buddypress', 'bp-disable-blogforum-comments', 'bp_admin_sanitize_callback_blogforum_comments' );

			// Activity Heartbeat refresh
			add_settings_field( '_bp_enable_heartbeat_refresh', __( 'Activity auto-refresh', 'buddypress' ), 'bp_admin_setting_callback_heartbeat', 'buddypress', 'bp_activity' );
			register_setting( 'buddypress', '_bp_enable_heartbeat_refresh', 'intval' );

			// Allow activity akismet
			if ( is_plugin_active( 'akismet/akismet.php' ) && defined( 'AKISMET_VERSION' ) ) {
				add_settings_field( '_bp_enable_akismet', __( 'Akismet',          'buddypress' ), 'bp_admin_setting_callback_activity_akismet', 'buddypress', 'bp_activity' );
				register_setting  ( 'buddypress',         '_bp_enable_akismet',   'intval'                                                                                  );
			}
		}

		/** Avatar upload for users or groups ************************************/

		if ( ! empty( $avatar_setting ) ) {
		    // Allow avatar uploads
		    add_settings_field( 'bp-disable-avatar-uploads', __( 'Profile Photo Uploads',   'buddypress' ), 'bp_admin_setting_callback_avatar_uploads',   'buddypress', $avatar_setting );
		    register_setting  ( 'buddypress',         'bp-disable-avatar-uploads',   'intval'                                                                                    );
		}
	}

	/**
	 * Add Settings link to plugins area.
	 *
	 * @since BuddyPress (1.6.0)
	 *
	 * @param array $links Links array in which we would prepend our link.
	 * @param string $file Current plugin basename.
	 * @return array Processed links.
	 */
	public function modify_plugin_action_links( $links, $file ) {

		// Return normal links if not BuddyPress
		if ( plugin_basename( buddypress()->basename ) != $file )
			return $links;

		// Add a few links to the existing links array
		return array_merge( $links, array(
			'dashboard' => '<a href="' . add_query_arg( array( 'page' => 'bp-about' ), bp_get_admin_url( 'index.php' ) ) . '">' . _x( 'Dashboard', 'Plugins screen dashboard link', 'buddypress' ) . '</a>',
			'settings'  => '<a href="' . add_query_arg( array( 'page' => 'bp-components' ), bp_get_admin_url( $this->settings_page ) ) . '">' . _x( 'Settings', 'Plugins screen settings link', 'buddypress' ) . '</a>',
		) );
	}

	/**
	 * Add some general styling to the admin area.
	 *
	 * @since BuddyPress (1.6.0)
	 */
	public function admin_head() {

		// Settings pages
		remove_submenu_page( $this->settings_page, 'bp-page-settings' );
		remove_submenu_page( $this->settings_page, 'bp-settings'      );

		// Network Admin Tools
		remove_submenu_page( 'network-tools', 'network-tools' );
	}

	/**
	 * Add some general styling to the admin area.
	 *
	 * @since BuddyPress (1.6.0)
	 */
	public function enqueue_scripts() {
		$min = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		$file = $this->css_url . "common{$min}.css";
		$file = apply_filters( 'bp_core_admin_common_css', $file );
		wp_enqueue_style( 'bp-admin-common-css', $file, array(), bp_get_version() );

		wp_style_add_data( 'bp-admin-common-css', 'rtl', true );
		if ( $min ) {
			wp_style_add_data( 'bp-admin-common-css', 'suffix', $min );
		}
	}


	/**
	 * Screens
	 */

	/**
	 * Output the about screen.
	 *
	 * @since BuddyPress (1.7.0)
	 */
	public function about_screen() {
		self::screen_header();
		echo '<p>in about_screen</p>';
		self::screen_footer();
	}


	/**
	 * Static helper/template functions.
	 */

	/**
	 * Outputs the common header template for the BP wp-admin screens.
	 *
	 * @param string $version Optional identifier to help support future changes backpat.
	 * @since BuddyPress (2.2.0)
	 */
	public static function screen_header( $version = '' ) {
		$html = '<div class="wrap">';      // Closed in screen_footer()
		$nav  = bp_core_get_admin_tabs();

		// Header block wrapper
		$html .= '<div class="bpa-header bpa-width-full clearfix">';

		// Small BP logo
		$html .= sprintf( '<p class="bpa-header-logo" aria-hidden="true">%s</p>',
			'<span class="screen-reader-text">' . __( 'BuddyPress', 'buddypress' ) . '</span>'
		);

		// Main navigation
		if ( ! empty( $nav ) ) {
			$html .= '<ul class="bpa-nav" role="navigation">';

			foreach ( $nav as $item ) {
				$html .= sprintf(
					'<li><a href="%s">%s</a></li>',
					esc_url( $item['href'] ),
					esc_html( $item['name'] )
				);
			}

			$html .= '</ul>';  // .bpa-nav
		}

		$html .= '</div>';  // .bpa-header
		echo $html;
	}

	/**
	 * Outputs the common footer template for the BP wp-admin screens.
	 *
	 * @param string $version Optional identifier to help support future changes backpat.
	 * @since BuddyPress (2.2.0)
	 */
	public static function screen_footer( $version = '' ) {
		echo '</div>';  // Closes .wrap in screen_header()
	}
}
endif; // class_exists check

/**
 * Setup BuddyPress Admin.
 *
 * @since BuddyPress (1.6.0)
 *
 * @uses BP_Admin
 */
function bp_admin() {
	buddypress()->admin = new BP_Admin();
	return;


	// These are strings we may use to describe maintenance/security releases, where we aim for no new strings.

	_n_noop( 'Maintenance Release', 'Maintenance Releases', 'buddypress' );
	_n_noop( 'Security Release', 'Security Releases', 'buddypress' );
	_n_noop( 'Maintenance and Security Release', 'Maintenance and Security Releases', 'buddypress' );

	/* translators: 1: WordPress version number. */
	_n_noop( '<strong>Version %1$s</strong> addressed a security issue.',
	         '<strong>Version %1$s</strong> addressed some security issues.',
	         'buddypress' );

	/* translators: 1: WordPress version number, 2: plural number of bugs. */
	_n_noop( '<strong>Version %1$s</strong> addressed %2$s bug.',
	         '<strong>Version %1$s</strong> addressed %2$s bugs.',
	         'buddypress' );

	/* translators: 1: WordPress version number, 2: plural number of bugs. Singular security issue. */
	_n_noop( '<strong>Version %1$s</strong> addressed a security issue and fixed %2$s bug.',
	         '<strong>Version %1$s</strong> addressed a security issue and fixed %2$s bugs.',
	         'buddypress' );

	/* translators: 1: WordPress version number, 2: plural number of bugs. More than one security issue. */
	_n_noop( '<strong>Version %1$s</strong> addressed some security issues and fixed %2$s bug.',
	         '<strong>Version %1$s</strong> addressed some security issues and fixed %2$s bugs.',
	         'buddypress' );

	__( 'For more information, see <a href="%s">the release notes</a>.', 'buddypress' );
}
