<?php
/**
 * Plugin Name:       Google Analytics Event Tracking for Gravity Forms
 * Plugin URI:        https://github.com/cleancoded/google-analytics-event-tracking-gravity-forms/
 * Description:       Easily add Google Analytics event tracking to Gravity Forms.
 * Version:           2.1.0
 * Author:            CLEANCODED
 * Author URI:        https://cleancoded.com
 * Text Domain:       google-analytics-event-tracking-gravity-forms
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Domain Path:       /languages
 * Developer Credit:  James Bregenzer
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

class GAETGF {

	/**
	 * Holds the class instance.
	 *
	 * @since 2.0.0
	 * @access private
	 */
	private static $instance = null;

	/**
	 * Retrieve a class instance.
	 *
	 * @since 2.0.0
	 */
	public static function get_instance() {
		if ( null == self::$instance ) {
			self::$instance = new self;
		}
		return self::$instance;
	} //end get_instance

	/**
	 * Class constructor.
	 *
	 * @since 2.0.0
	 */
	private function __construct() {
		load_plugin_textdomain( 'gravity-forms-google-analytics-event-tracking', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );

		spl_autoload_register( array( $this, 'loader' ) );

		add_action( 'gform_loaded', array( $this, 'gforms_loaded' ) );
	}

	/**
	 * Check for the minimum supported PHP version.
	 *
	 * @since 2.0.0
	 *
	 * @return bool true if meets minimum version, false if not
	 */
	public static function check_php_version() {
		if( ! version_compare( '5.3', PHP_VERSION, '<=' ) ) {
			return false;
		}
		return true;
	}

	/**
	 * Check the plugin to make sure it meets the minimum requirements.
	 *
	 * @since 2.0.0
	 */
	public static function check_plugin() {
		if( ! GAETGF::check_php_version() ) {
			deactivate_plugins( GAETGF::get_plugin_basename() );
			exit( sprintf( esc_html__( 'Gravity Forms Event Tracking requires PHP version 5.3 and up. You are currently running PHP version %s.', 'gravity-forms-google-analytics-event-tracking' ), esc_html( PHP_VERSION ) ) );
		}
	}

	/**
	 * Retrieve the plugin basename.
	 *
	 * @since 2.0.0
	 *
	 * @return string plugin basename
	 */
	public static function get_plugin_basename() {
		return plugin_basename( __FILE__ );
	}

	/**
	 * Return the absolute path to an asset.
	 *
	 * @since 2.0.0
	 *
	 * @param string @path Relative path to the asset.
	 *
	 * return string Absolute path to the relative asset.
	 */
	public static function get_plugin_dir( $path = '' ) {
		$dir = rtrim( plugin_dir_path(__FILE__), '/' );
		if ( !empty( $path ) && is_string( $path) )
			$dir .= '/' . ltrim( $path, '/' );
		return $dir;
	}

	/**
	 * Initialize Gravity Forms related add-ons.
	 *
	 * @since 2.0.0
	 */
	public function gforms_loaded() {
		if ( ! GAETGF::check_php_version() ) return;
		if ( ! method_exists( 'GFForms', 'include_feed_addon_framework' ) ) {
			return;
		}

		// Initialize settings screen and feeds
		GFAddOn::register( 'GAETGF_UA' );
		GFAddOn::register( 'GAETGF_Submission_Feeds' );

		// Initialize pagination
		add_action( 'gform_post_paging', array( $this, 'pagination'), 10, 3 );

		// Initialize whether Ajax is on or off
		add_filter( 'gform_form_args', array( $this, 'maybe_ajax_only' ), 15, 1 );
	}

	/**
	 * Get the Google Analytics UA Code
	 *
	 * @since 2.0.0
	 * @return string/bool Returns string UA code, false otherwise
	 */
	public static function get_ua_code() {
		$gravity_forms_add_on_settings = get_option( 'gravityformsaddon_GAETGF_UA_settings', array() );

		$ua_id = isset( $gravity_forms_add_on_settings[ 'gravity_forms_event_tracking_ua' ] ) ? $gravity_forms_add_on_settings[ 'gravity_forms_event_tracking_ua' ] : false;

		$ua_regex = "/^UA-[0-9]{5,}-[0-9]{1,}$/";

		if ( preg_match( $ua_regex, $ua_id ) ) {
			return $ua_id;
		}
		return false;
	}

	/**
	 * Checks whether Google Analytics mode is activated for sending events.
	 *
	 * @since 2.0.0
	 *
	 * @return bool true if GA only, false if not
	 */
	public static function is_ga_only() {
		$ga_options = get_option( 'gravityformsaddon_GAETGF_UA_settings', false );
		if ( ! isset( $ga_options[ 'mode' ] ) ) {
			return false;
		}
		if ( 'ga' == $ga_options[ 'mode' ] ) {
			return true;
		}
		return false;
	}

	/**
	 * Checks whether Tag Manager only mode is activated for sending events.
	 *
	 * @since 2.0.0
	 *
	 * @return bool true if GTM only, false if not
	 */
	public static function is_gtm_only() {
		$ga_options = get_option( 'gravityformsaddon_GAETGF_UA_settings', false );
		if ( ! isset( $ga_options[ 'mode' ] ) ) {
			return false;
		}
		if ( 'gtm' == $ga_options[ 'mode' ] ) {
			return true;
		}
		return false;
	}

	/**
	 * Autoload class files.
	 *
	 * @since 2.0.0
	 *
	 * @param string $class_name The class name
	 */
	public function loader( $class_name ) {
		if ( class_exists( $class_name, false ) || false === strpos( $class_name, 'GAETGF' ) ) {
			return;
		}
		$file = GAETGF::get_plugin_dir( "includes/{$class_name}.php" );
		if ( file_exists( $file ) ) {
			include_once( $file );
		}
	}

	/**
	 * Sets all forms to Ajax only depeneding on settings
	 *
	 * @since 2.0.0
	 *
	 * @param array $form_args The form arguments
	 */
	public function maybe_ajax_only( $form_args ) {
		$gravity_forms_add_on_settings = get_option( 'gravityformsaddon_GAETGF_UA_settings', array() );

		if ( isset( $gravity_forms_add_on_settings[ 'ajax_only' ] ) && 'on' == $gravity_forms_add_on_settings[ 'ajax_only' ] ) {
			$form_args[ 'ajax' ] = true;
		}
		return $form_args;
	}

	/**
	 * Initialize the pagination events.
	 *
	 * @since 2.0.0
	 *
	 * @param array $form                The form arguments
	 * @param int   @source_page_number  The original page number
	 * @param int   $current_page_number The new page number
	 */
	public function pagination( $form, $source_page_number, $current_page_number ) {
		$pagination = GAETGF_Pagination::get_instance();
		$pagination->paginate( $form, $source_page_number, $current_page_number );
	}
}

register_activation_hook( __FILE__, array( 'GAETGF', 'check_plugin' ) );
GAETGF::get_instance();
