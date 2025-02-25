<?php
/**
 * Plugin Name: WP Google Drive Integration
 * Plugin URI: https://example.com/wp-google-drive-integration
 * Description: Integrate Google Drive functionality into WordPress
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://example.com
 * Text Domain: wp-google-drive
 * License: GPL-2.0+
 */

// If this file is called directly, abort.
use App\CacheService;
use App\GoogleDriveService;

if ( ! defined( 'WPINC' ) ) {
	die;
}

// Define plugin constants
define( 'WP_GDRIVE_VERSION', '1.0.0' );
define( 'WP_GDRIVE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WP_GDRIVE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WP_GDRIVE_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'WP_GDRIVE_CACHE_DIR', WP_GDRIVE_PLUGIN_DIR . 'cache/' );
define( 'WP_GDRIVE_CREDENTIALS_FILE', WP_GDRIVE_PLUGIN_DIR . 'credentials.json' );

// Load Composer autoloader
if ( file_exists( WP_GDRIVE_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
	require_once WP_GDRIVE_PLUGIN_DIR . 'vendor/autoload.php';
} else {
	add_action( 'admin_notices', function () {
		echo '<div class="error"><p>WP Google Drive Integration requires Composer dependencies to be installed. Please run <code>composer install</code> in the plugin directory.</p></div>';
	} );

	return;
}

// Initialize default settings if they don't exist
add_action( 'admin_init', function () {
	if ( get_option( 'wp_gdrive_default_folder_id' ) === false ) {
		add_option( 'wp_gdrive_default_folder_id', '' );
	}
	if ( get_option( 'wp_gdrive_app_name' ) === false ) {
		add_option( 'wp_gdrive_app_name', 'WordPress Google Drive Integration' );
	}
	if ( get_option( 'wp_gdrive_service_account' ) === false ) {
		add_option( 'wp_gdrive_service_account', '' );
	}
	if ( get_option( 'wp_gdrive_cache_lifetime' ) === false ) {
		add_option( 'wp_gdrive_cache_lifetime', 3600 );
	}
} );

// Include required files
require_once WP_GDRIVE_PLUGIN_DIR . 'src/CacheService.php';
require_once WP_GDRIVE_PLUGIN_DIR . 'src/GoogleDriveService.php';
require_once WP_GDRIVE_PLUGIN_DIR . 'src/change_folder.php';

/**
 * Main plugin class
 */
class WP_Google_Drive {
	/**
	 * @var WP_Google_Drive
	 */
	private static $instance;

	/**
	 * @var \App\GoogleDriveService
	 */
	public $driveService;

	/**
	 * @var \App\CacheService
	 */
	public $cacheService;

	/**
	 * Get the singleton instance
	 *
	 * @return WP_Google_Drive
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor
	 */
	private function __construct() {
		// Initialize services
		$this->cacheService = new CacheService( WP_GDRIVE_CACHE_DIR );
		$this->driveService = new GoogleDriveService(
			WP_GDRIVE_CREDENTIALS_FILE,
			$this->cacheService
		);

		// Register activation/deactivation hooks
		register_activation_hook( __FILE__, [ $this, 'activate' ] );
		register_deactivation_hook( __FILE__, [ $this, 'deactivate' ] );

		// Initialize the plugin
		add_action( 'plugins_loaded', [ $this, 'init' ] );
	}

	/**
	 * Plugin activation
	 */
	public function activate() {
		// Create cache directory if it doesn't exist
		if ( ! file_exists( WP_GDRIVE_CACHE_DIR ) ) {
			wp_mkdir_p( WP_GDRIVE_CACHE_DIR );
		}

		// Create .htaccess file to protect cache directory
		$htaccess_file = WP_GDRIVE_CACHE_DIR . '.htaccess';
		if ( ! file_exists( $htaccess_file ) ) {
			$htaccess_content = "Deny from all\n";
			file_put_contents( $htaccess_file, $htaccess_content );
		}

		flush_rewrite_rules();
	}

	/**
	 * Plugin deactivation
	 */
	public function deactivate() {
		flush_rewrite_rules();
	}

	/**
	 * Initialize the plugin
	 */
	public function init() {
		// Load text domain for internationalization
		load_plugin_textdomain( 'wp-google-drive', false, dirname( WP_GDRIVE_PLUGIN_BASENAME ) . '/languages' );

		// Register admin menu
		add_action( 'admin_menu', [ $this, 'register_admin_menu' ] );

		// Register settings
		add_action( 'admin_init', [ $this, 'register_settings' ] );

		// Register shortcodes
		add_shortcode( 'wp_gdrive_folder', [ $this, 'folder_shortcode' ] );

		// Register AJAX handlers
		add_action( 'wp_ajax_wp_gdrive_change_folder', 'App\change_folder' );
		add_action( 'wp_ajax_nopriv_wp_gdrive_change_folder', 'App\change_folder' );

		// Enqueue scripts and styles
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_frontend_assets' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
	}

	/**
	 * Register plugin settings
	 */
	public function register_settings() {
		register_setting( 'wp_gdrive_settings', 'wp_gdrive_app_name' );
		register_setting( 'wp_gdrive_settings', 'wp_gdrive_default_folder_id' );
		register_setting( 'wp_gdrive_settings', 'wp_gdrive_service_account' );
		register_setting( 'wp_gdrive_settings', 'wp_gdrive_cache_lifetime', [
			'type'              => 'integer',
			'sanitize_callback' => 'absint',
			'default'           => 3600
		] );
	}

	/**
	 * Register admin menu
	 */
	public function register_admin_menu() {
		add_menu_page(
			__( 'Google Drive Integration', 'wp-google-drive' ),
			__( 'Google Drive', 'wp-google-drive' ),
			'manage_options',
			'wp-gdrive-settings',
			[ $this, 'render_settings_page' ],
			'dashicons-cloud',
			81
		);
	}

	/**
	 * Render settings page
	 */
	public function render_settings_page() {
		// Check if credentials file exists
		$credentials_exist = file_exists( WP_GDRIVE_CREDENTIALS_FILE );

		// Check if service is authenticated
		$is_authenticated = $this->driveService->isAuthenticated();

		include WP_GDRIVE_PLUGIN_DIR . 'templates/admin-settings.php';
	}

	/**
	 * Enqueue frontend assets
	 */
	public function enqueue_frontend_assets() {
		wp_enqueue_style(
			'wp-gdrive-frontend',
			WP_GDRIVE_PLUGIN_URL . 'assets/css/frontend.css',
			[],
			WP_GDRIVE_VERSION
		);

		wp_enqueue_script(
			'wp-gdrive-frontend',
			WP_GDRIVE_PLUGIN_URL . 'assets/js/frontend.js',
			[ 'jquery' ],
			WP_GDRIVE_VERSION,
			true
		);

		wp_localize_script( 'wp-gdrive-frontend', 'wpGDrive', [
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'wp-gdrive-nonce' ),
		] );
	}

	/**
	 * Enqueue admin assets
	 */
	public function enqueue_admin_assets( $hook ) {
		if ( 'toplevel_page_wp-gdrive-settings' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'wp-gdrive-admin',
			WP_GDRIVE_PLUGIN_URL . 'assets/css/admin.css',
			[],
			WP_GDRIVE_VERSION
		);

		wp_enqueue_script(
			'wp-gdrive-admin',
			WP_GDRIVE_PLUGIN_URL . 'assets/js/admin.js',
			[ 'jquery' ],
			WP_GDRIVE_VERSION,
			true
		);

		wp_localize_script( 'wp-gdrive-admin', 'wpGDrive', [
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'wp-gdrive-admin-nonce' ),
		] );
	}

	/**
	 * Folder shortcode handler
	 *
	 * @param array $atts Shortcode attributes
	 *
	 * @return string Shortcode output
	 */
	public function folder_shortcode( $atts ) {
		$atts = shortcode_atts( [
			'folder_id'        => '',
			'title'            => '',
			'show_breadcrumbs' => 'true',
		], $atts, 'wp_gdrive_folder' );

		if ( empty( $atts['folder_id'] ) ) {
			return '<div class="wp-gdrive-error">' . __( 'No folder ID specified', 'wp-google-drive' ) . '</div>';
		}

		try {
			$folder_id = sanitize_text_field( $atts['folder_id'] );
			$this->driveService->changeFolder( $folder_id );
			$folder_contents = $this->driveService->getFolderContents();

			ob_start();
			include WP_GDRIVE_PLUGIN_DIR . 'templates/folder-contents.php';

			return ob_get_clean();
		} catch ( \Exception $e ) {
			return '<div class="wp-gdrive-error">' . esc_html( $e->getMessage() ) . '</div>';
		}
	}
}

// Initialize the plugin
function wp_google_drive() {
	return WP_Google_Drive::get_instance();
}

// Start the plugin
wp_google_drive();