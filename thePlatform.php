<?php
/*
  Plugin Name: thePlatform Video Manager
  Plugin URI: http://theplatform.com/
  Description: Manage video assets hosted in thePlatform MPX from within WordPress.
  Version: 1.5.0
  Author: thePlatform
  Author URI: http://theplatform.com/
  License: GPL2

  Copyright 2013-2015 thePlatform LLC

  This program is free software; you can redistribute it and/or modify
  it under the terms of the GNU General Public License, version 2, as
  published by the Free Software Foundation.

  This program is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.

  You should have received a copy of the GNU General Public License
  along with this program; if not, write to the Free Software
  Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * This is thePlatform's plugin entry class, all initalization and AJAX handlers are defined here.
 */
class ThePlatform_Plugin {

	private $plugin_base_dir;
	private $plugin_base_url;
	private static $instance;

	/**
	 * Creates one instance of the plugin
	 * @return ThePlatform_Plugin New or existing instance of ThePlatform_Plugin
	 */
	public static function init() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new ThePlatform_Plugin;
		}

		return self::$instance;
	}

	/**
	 * Class constructor
	 */
	function __construct() {
		require_once( dirname( __FILE__ ) . '/thePlatform-constants.php' );
		require_once( dirname( __FILE__ ) . '/thePlatform-proxy.php' );
		require_once( dirname( __FILE__ ) . '/thePlatform-helper.php' );

		$this->plugin_base_dir = plugin_dir_path( __FILE__ );
		$this->plugin_base_url = plugins_url( '/', __FILE__ );

		add_action( 'admin_menu', array( $this, 'add_admin_page' ) );
		add_action( 'admin_init', array( $this, 'register_scripts' ) );
		add_action( 'admin_init', array( $this, 'theplatform_register_plugin_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );

		add_filter( 'media_upload_tabs', array( $this, 'tp_upload_tab' ) );
		add_action( 'media_upload_mytabname', array( $this, 'add_tp_media_form' ) );


		add_shortcode( 'theplatform', array( $this, 'shortcode' ) );
	}

	function tp_upload_tab( $tabs ) {
		$tabs['mytabname'] = "thePlatform";

		return $tabs;
	}

// call the new tab with wp_iframe

	function add_tp_media_form() {
		wp_iframe( array( $this, 'tp_media_form' ) );
	}

// the tab content
	function tp_media_form() {
		require_once( dirname( __FILE__ ) . '/thePlatform-media.php' );
	}

	function admin_enqueue_scripts( $hook ) {
		// echo $hook;
		// wp_enqueue_style( 'tp_bootstrap_css', 'https://maxcdn.bootstrapcdn.com/bootstrap/3.3.2/css/bootstrap.min.css' );
		// Media Browser		
		if ( $hook == 'toplevel_page_theplatform' || $hook == 'media-upload-popup' ) {
			if ( ! isset( $_GET['embed'] ) ) {
				wp_enqueue_script( 'tp_edit_upload_js' );
			}
			wp_enqueue_script( 'tp_browser_js' );
			wp_enqueue_style( 'tp_browser_css' );
		}
		// Edit/Upload Form
		if ( $hook == 'theplatform_page_theplatform-uploader' ) {
			wp_enqueue_script( 'tp_edit_upload_js' );
			wp_enqueue_style( 'tp_edit_upload_css' );
		}
		// Upload popup
		if ( $hook == 'admin_page_theplatform-upload-window' ) {
			wp_enqueue_script( 'tp_file_uploader_js' );
			wp_enqueue_style( 'tp_file_uploader_css' );
		}

	}

	/**
	 * Registers initial plugin settings during initialization
	 */
	function theplatform_register_plugin_settings() {
		register_setting( TP_ACCOUNT_OPTIONS_KEY, TP_ACCOUNT_OPTIONS_KEY, 'theplatform_account_options_validate' );
		register_setting( TP_PREFERENCES_OPTIONS_KEY, TP_PREFERENCES_OPTIONS_KEY, 'theplatform_preferences_options_validate' );
		register_setting( TP_CUSTOM_METADATA_OPTIONS_KEY, TP_CUSTOM_METADATA_OPTIONS_KEY, 'theplatform_dropdown_options_validate' );
		register_setting( TP_BASIC_METADATA_OPTIONS_KEY, TP_BASIC_METADATA_OPTIONS_KEY, 'theplatform_dropdown_options_validate' );
		register_setting( TP_TOKEN_OPTIONS_KEY, TP_TOKEN_OPTIONS_KEY, 'strval' );
	}

	/**
	 * Registers javascripts and css used throughout the plugin
	 */
	function register_scripts() {
		wp_register_script( 'tp_pdk_js', "//pdk.theplatform.com/next/pdk/tpPdk.js" );
		wp_register_script( 'tp_holder_js', plugins_url( '/js/holder.js', __FILE__ ) );
		wp_register_script( 'tp_media_button_js', plugins_url( '/js/thePlatform-media-button.js', __FILE__ ), array( 'jquery-ui-dialog' ) );
		wp_register_script( 'tp_nprogress_js', plugins_url( '/js/nprogress.js', __FILE__ ) );
		wp_register_script( 'tp_edit_upload_js', plugins_url( '/js/thePlatform-edit-upload.js', __FILE__ ), array( 'jquery' ) );
		wp_register_script( 'tp_file_uploader_js', plugins_url( '/js/theplatform-uploader.js', __FILE__ ), array( 'jquery', 'tp_nprogress_js' ) );
		wp_register_script( 'tp_browser_js', plugins_url( '/js/thePlatform-browser.js', __FILE__ ), array( 'jquery', 'underscore', 'backbone', 'jquery-ui-dialog', 'tp_holder_js', 'tp_pdk_js', 'tp_nprogress_js' ) );
		wp_register_script( 'tp_options_js', plugins_url( '/js/thePlatform-options.js', __FILE__ ), array( 'jquery', 'jquery-ui-sortable' ) );


		wp_localize_script( 'tp_edit_upload_js', 'tp_edit_upload_local', array(
			'ajaxurl'             => admin_url( 'admin-ajax.php' ),
			'uploader_window_url' => admin_url( 'admin.php?page=theplatform-upload-window' ),
			'tp_nonce'            => array(
				'theplatform_edit'    => wp_create_nonce( 'theplatform-ajax-nonce-theplatform_edit' ),
				'theplatform_media'   => wp_create_nonce( 'theplatform-ajax-nonce-theplatform_media' ),
				'theplatform_upload'  => wp_create_nonce( 'theplatform-ajax-nonce-theplatform_upload' ),
				'theplatform_publish' => wp_create_nonce( 'theplatform-ajax-nonce-publish_media' ),
				'theplatform_revoke'  => wp_create_nonce( 'theplatform-ajax-nonce-revoke_media' )
			)
		) );

		wp_localize_script( 'tp_file_uploader_js', 'tp_file_uploader_local', array(
			'ajaxurl'             => admin_url( 'admin-ajax.php' ),
			'uploader_window_url' => $this->plugin_base_url . 'theplatform-upload-window.php',
			'tp_nonce'            => array(
				'initialize_media_upload' => wp_create_nonce( 'theplatform-ajax-nonce-initialize_media_upload' ),
				'publish_media'           => wp_create_nonce( 'theplatform-ajax-nonce-publish_media' )
			)
		) );

		wp_localize_script( 'tp_browser_js', 'tp_browser_local', array(
			'ajaxurl'  => admin_url( 'admin-ajax.php' ),
			'tp_nonce' => array(
				'get_videos'          => wp_create_nonce( 'theplatform-ajax-nonce-get_videos' ),
				'get_video_by_id'     => wp_create_nonce( 'theplatform-ajax-nonce-get_video_by_id' ),
				'get_categories'      => wp_create_nonce( 'theplatform-ajax-nonce-get_categories' ),
				'get_profile_results' => wp_create_nonce( 'theplatform-ajax-nonce-get_profile_results' ),
				'set_thumbnail'       => wp_create_nonce( 'theplatform-ajax-nonce-set_thumbnail' ),
				'generate_thumbnail'  => wp_create_nonce( 'theplatform-ajax-nonce-generate_thumbnail' )
			)
		) );

		wp_localize_script( 'tp_options_js', 'tp_options_local', array(
			'ajaxurl'  => admin_url( 'admin-ajax.php' ),
			'tp_nonce' => array(
				'verify_account' => wp_create_nonce( 'theplatform-ajax-nonce-verify_account' )
			)
		) );

		wp_register_style( 'tp_edit_upload_css', plugins_url( '/css/thePlatform-edit-upload.css', __FILE__ ) );
		wp_register_style( 'tp_browser_css', plugins_url( '/css/thePlatform-browser.css', __FILE__ ), array( 'tp_edit_upload_css', 'wp-jquery-ui-dialog', 'tp_nprogress_css' ) );
		wp_register_style( 'tp_options_css', plugins_url( '/css/thePlatform-options.css', __FILE__ ) );
		wp_register_style( 'tp_nprogress_css', plugins_url( '/css/nprogress.css', __FILE__ ) );
		wp_register_style( 'tp_file_uploader_css', plugins_url( '/css/thePlatform-file-uploader.css', __FILE__ ), array( 'tp_nprogress_css' ) );
	}

	/**
	 * Add admin pages to Wordpress sidebar
	 */
	function add_admin_page() {
		$tp_admin_cap    = apply_filters( TP_ADMIN_CAP, TP_ADMIN_DEFAULT_CAP );
		$tp_viewer_cap   = apply_filters( TP_VIEWER_CAP, TP_VIEWER_DEFAULT_CAP );
		$tp_uploader_cap = apply_filters( TP_UPLOADER_CAP, TP_UPLOADER_DEFAULT_CAP );
		$slug            = 'theplatform';
		add_menu_page( 'thePlatform', 'thePlatform', $tp_viewer_cap, $slug, array( $this, 'media_page' ), 'dashicons-video-alt3', '10.0912' );
		add_submenu_page( $slug, 'thePlatform Video Browser', 'Browse MPX Media', $tp_viewer_cap, $slug, array( $this, 'media_page' ) );
		add_submenu_page( $slug, 'thePlatform Video Uploader', 'Upload Media to MPX', $tp_uploader_cap, 'theplatform-uploader', array( $this, 'upload_page' ) );
		add_submenu_page( $slug, 'thePlatform Plugin Settings', 'Settings', $tp_admin_cap, 'theplatform-settings', array( $this, 'admin_page' ) );
		add_submenu_page( $slug, 'thePlatform Plugin About', 'About', $tp_admin_cap, 'theplatform-about', array( $this, 'about_page' ) );
		add_submenu_page( 'options.php', 'thePlatform Plugin Uploader', 'Uploader', $tp_uploader_cap, 'theplatform-upload-window', array( $this, 'upload_window' ) );
	}

	/**
	 * Calls the plugin's options page template
	 */
	function admin_page() {
		theplatform_check_plugin_update();
		require_once( dirname( __FILE__ ) . '/thePlatform-options.php' );
	}

	/**
	 * Calls the Media Manager template
	 */
	function media_page() {
		theplatform_check_plugin_update();
		require_once( dirname( __FILE__ ) . '/thePlatform-media.php' );
	}

	/**
	 * Calls the Upload form template
	 */
	function upload_page() {
		theplatform_check_plugin_update();
		require_once( dirname( __FILE__ ) . '/thePlatform-edit-upload.php' );
	}

	/**
	 * Calls the About page template
	 */
	function about_page() {
		require_once( dirname( __FILE__ ) . '/thePlatform-about.php' );
	}

	function upload_window() {
		require_once( $this->plugin_base_dir . 'thePlatform-upload-window.php' );
	}

	/**
	 * Shortcode Callback
	 *
	 * @param array $atts Shortcode attributes
	 *
	 * @return string thePlatform video embed shortcode
	 */
	function shortcode( $atts ) {
		if ( ! class_exists( 'ThePlatform_API' ) ) {
			require_once( dirname( __FILE__ ) . '/thePlatform-API.php' );
		}

		if ( ! isset( $this->preferences ) ) {
			$this->preferences = get_option( TP_PREFERENCES_OPTIONS_KEY );
		}

		if ( ! isset( $this->account ) ) {
			$this->account = get_option( TP_ACCOUNT_OPTIONS_KEY );
		}

		list( $account, $width, $height, $media, $player, $mute, $autoplay, $loop, $tag, $embedded, $params ) = array_values( shortcode_atts( array(
				'account'  => '',
				'width'    => '',
				'height'   => '',
				'media'    => '',
				'player'   => '',
				'mute'     => '',
				'autoplay' => '',
				'loop'     => '',
				'tag'      => '',
				'embedded' => '',
				'params'   => ''
			), $atts
			)
		);

		if ( empty( $width ) ) {
			$width = (int) $this->preferences['default_width'];
		}
		if ( strval( $width ) === '0' ) {
			$width = 500;
		}

		if ( empty( $height ) ) {
			$height = $this->preferences['default_height'];
		}
		if ( strval( $height ) === '0' ) {
			$height = floor( $width * 9 / 16 );
		}

		$mute     = $this->check_shortcode_parameter( $mute, 'false', array( 'true', 'false' ) );
		$loop     = $this->check_shortcode_parameter( $loop, 'false', array( 'true', 'false' ) );
		$autoplay = $this->check_shortcode_parameter( $autoplay, $this->preferences['autoplay'], array( 'false', 'true' ) );
		$embedded = $this->check_shortcode_parameter( $embedded, $this->preferences['player_embed_type'], array( 'true', 'false' ) );
		$tag      = $this->check_shortcode_parameter( $tag, $this->preferences['embed_tag_type'], array( 'iframe', 'script' ) );

		if ( empty( $media ) ) {
			return '<!--Syntax Error: Required Media parameter missing. -->';
		}

		if ( empty( $player ) ) {
			return '<!--Syntax Error: Required Player parameter missing. -->';
		}

		if ( empty ( $account ) ) {
			$account = $this->account['mpx_account_pid'];
		}


		if ( ! is_feed() ) {
			$output = $this->get_embed_shortcode( $account, $media, $player, $width, $height, $autoplay, $tag, $embedded, $loop, $mute, $params );
			$output = apply_filters( 'tp_embed_code', $output );
		} else {
			switch ( $this->preferences['rss_embed_type'] ) {
				case 'article':
					$output = '[Sorry. This video cannot be displayed in this feed. <a href="' . get_permalink() . '">View your video here.]</a>';
					break;
				case 'iframe':
					$output = $this->get_embed_shortcode( $account, $media, $player, $width, $height, $autoplay, 'iframe', $embedded, $loop, $mute, $params );
					break;
				case 'script':
					$output = $this->get_embed_shortcode( $account, $media, $player, $width, $height, $autoplay, 'script', $embedded, $loop, $mute, $params );
					break;
				default:
					$output = '[Sorry. This video cannot be displayed in this feed. <a href="' . get_permalink() . '">View your video here.]</a>';
					break;
			}
			$output = apply_filters( 'tp_rss_embed_code', $output );
		}

		return $output;
	}

	/**
	 * Checks a shortcode value is valid and if not returns a default value
	 *
	 * @param string $value The shortcode parameter value
	 * @param string $defaultValue The default value to return if a user entered an invalid entry.
	 * @param array $allowedValues An array of valid values for the shortcode parameter
	 *
	 * @return string The final value
	 */
	function check_shortcode_parameter( $value, $defaultValue, $allowedValues ) {

		$value = strtolower( $value );

		if ( empty ( $value ) ) {
			return $defaultValue;
		} else if ( in_array( $value, $allowedValues ) ) {
			return $value;
		}

		if ( ! empty ( $defaultValue ) ) {
			return $defaultValue;
		}

		return $allowedValues[0];
	}

	/**
	 * Called by the plugin shortcode callback function to construct a media embed iframe.
	 *
	 * @param string $accountPID Account of the user embedding the media asset
	 * @param string $releasePID Identifier of the media object to embed
	 * @param string $playerPID Identifier of the player to display the embedded media asset in
	 * @param string $player_width The width of the embedded player
	 * @param string $player_height The height of the embedded player
	 * @param boolean $autoplay Whether or not to loop the embedded media automatically
	 * @param boolean $tag script or iframe embed tag style
	 * @param boolean $loop Set the embedded media to loop, false by default
	 * @param boolean $mute Whether or not to mute the audio channel of the embedded media asset, false by default
	 * @param string $params Any additional parameters to add to the embed code
	 *
	 * @return string An iframe tag sourced from the selected media embed URL
	 */
	function get_embed_shortcode( $accountPID, $releasePID, $playerPID, $player_width, $player_height, $autoplay, $tag, $embedded, $loop = false, $mute = false, $params = '' ) {

		$url = TP_API_PLAYER_EMBED_BASE_URL . urlencode( $accountPID ) . '/' . urlencode( $playerPID );

		if ( $embedded === 'true' ) {
			$url .= '/embed';
		}

		$url .= '/select/' . $releasePID;

		$url = apply_filters( 'tp_base_embed_url', $url );

		if ( $tag == 'script' ) {
			$url .= '?form=javascript';
		} else {
			$url .= '?form=html';
		}

		if ( $loop !== "false" ) {
			$url .= "&loop=true";
		}

		if ( $autoplay !== "false" ) {
			$url .= "&autoPlay=true";
		}

		if ( $mute !== "false" ) {
			$url .= "&mute=true";
		}

		if ( $params !== '' ) {
			$url .= '&' . $params;
		}

		if ( $embedded == 'false' && $tag == 'script' ) {
			$url .= '&videoHeight=' . $player_height . '&videoWidth=' . $player_width;
		}

		$url = apply_filters( 'tp_full_embed_url', $url );

		if ( $tag == "script" ) {
			return '<div class="tpEmbed" style="width:' . esc_attr( $player_width ) . 'px; height:' . esc_attr( $player_height ) . 'px;"><script type="text/javascript" src="' . esc_url_raw( $url ) . '"></script></div>';
		} else { //Assume iframe			
			return '<iframe class="tpEmbed" src="' . esc_url( $url ) . '" height="' . esc_attr( $player_height ) . '" width="' . esc_attr( $player_width ) . '" frameBorder="0" seamless="seamless" allowFullScreen></iframe>';
		}
	}
}

// Instantiate thePlatform plugin on WordPress init
add_action( 'init', array( 'ThePlatform_Plugin', 'init' ) );
add_action( 'wp_ajax_verify_account', 'theplatform_verify_account_settings' );





