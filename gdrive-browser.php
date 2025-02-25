<?php
/**
 * Plugin Name: Google Drive Integration
 * Plugin URI: https://example.com/plugins/google-drive-integration
 * Description: A WordPress plugin to integrate Google Drive functionality
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://example.com
 * Text Domain: google-drive-integration
 * License: GPL-2.0+
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

// Define plugin constants
const GDI_PLUGIN_VERSION = '1.0.0';
define( 'GDI_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'GDI_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
const GDI_CACHE_DIR        = GDI_PLUGIN_PATH . 'cache/';
const GDI_CREDENTIALS_FILE = GDI_PLUGIN_PATH . 'credentials/service-account.enc';
const GDI_CREDENTIALS_DIR  = GDI_PLUGIN_PATH . 'credentials/';

// Load Composer autoloader
require_once GDI_PLUGIN_PATH . 'vendor/autoload.php';

// Import classes
use App\GoogleDriveIntegration;

// Initialize the plugin
add_action( 'plugins_loaded', 'google_drive_integration_init' );
register_activation_hook( __FILE__, 'google_drive_integration_activate' );


function google_drive_integration_init(): void {
	GoogleDriveIntegration::get_instance();
}

/**
 * Create necessary directories on plugin activation
 */
function google_drive_integration_activate(): void {
	// Create cache directory if it doesn't exist
	if ( ! file_exists( GDI_CACHE_DIR ) ) {
		wp_mkdir_p( GDI_CACHE_DIR );
	}
}

