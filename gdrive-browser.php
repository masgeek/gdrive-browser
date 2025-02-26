<?php
/**
 * Plugin Name: Google Drive Browser
 * Plugin URI: https://github.com/masgeek/gdrive-browser/releases
 * Description: A WordPress plugin to integrate Google Drive functionality and browse the files
 * Version: 1.0.0
 * Author: Masgeek
 * Author URI: https://github.com/masgeek
 * Text Domain: gdrive-browser
 * License: GPL-2.0+
 * Requires PHP: 8.2
 * Requires at least: 5.8
 * Domain Path: /languages
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
	new \App\GoogleDriveShortCode();
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

