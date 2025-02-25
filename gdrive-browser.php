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
define( 'GDI_PLUGIN_VERSION', '1.0.0' );
define( 'GDI_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'GDI_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'GDI_CACHE_DIR', GDI_PLUGIN_PATH . 'cache/' );

// Load dependencies via Composer autoload
require_once GDI_PLUGIN_PATH . 'vendor/autoload.php';

// Load environment variables if .env exists
if ( file_exists( GDI_PLUGIN_PATH . '.env' ) ) {
	$dotenv = Dotenv\Dotenv::createImmutable( GDI_PLUGIN_PATH );
	$dotenv->load();
}

// Include plugin files
require_once GDI_PLUGIN_PATH . 'src/CacheService.php';
require_once GDI_PLUGIN_PATH . 'src/GoogleDriveService.php';
require_once GDI_PLUGIN_PATH . 'src/change_folder.php';

/**
 * Main plugin class
 */
class Google_Drive_Integration {

	/**
	 * @var Google_Drive_Integration
	 */
	private static $instance;

	/**
	 * @var \GoogleDriveService
	 */
	private $google_drive_service;

	/**
	 * @var \CacheService
	 */
	private $cache_service;

	/**
	 * Get singleton instance
	 *
	 * @return Google_Drive_Integration
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
		$this->init_services();

		// Add hooks
		$this->add_hooks();
	}

	/**
	 * Initialize services
	 */
	private function init_services() {
		// Initialize cache service
		$this->cache_service = new CacheService( GDI_CACHE_DIR );

		// Initialize Google Drive service using stored credentials
		$credentials_json = get_option( 'gdi_service_account_json', '' );
		$this->google_drive_service = new GoogleDriveService( $credentials_json, $this->cache_service );
	}

	/**
	 * Add WordPress hooks
	 */
	private function add_hooks() {
		// Admin menu
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );

		// Settings
		add_action( 'admin_init', array( $this, 'register_settings' ) );

		// AJAX handlers
		add_action( 'wp_ajax_gdi_change_folder', array( $this, 'ajax_change_folder' ) );
		add_action( 'wp_ajax_gdi_test_connection', array( $this, 'ajax_test_connection' ) );

		// Enqueue scripts and styles
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}

	/**
	 * Add admin menu
	 */
	public function add_admin_menu() {
		add_menu_page(
			__( 'Google Drive Integration', 'google-drive-integration' ),
			__( 'Google Drive', 'google-drive-integration' ),
			'manage_options',
			'google-drive-integration',
			array( $this, 'display_admin_page' ),
			'dashicons-cloud',
			30
		);

		add_submenu_page(
			'google-drive-integration',
			__( 'Files Browser', 'google-drive-integration' ),
			__( 'Files Browser', 'google-drive-integration' ),
			'manage_options',
			'google-drive-integration',
			array( $this, 'display_admin_page' )
		);

		add_submenu_page(
			'google-drive-integration',
			__( 'Settings', 'google-drive-integration' ),
			__( 'Settings', 'google-drive-integration' ),
			'manage_options',
			'google-drive-integration-settings',
			array( $this, 'display_settings_page' )
		);
	}

	/**
	 * Register plugin settings
	 */
	public function register_settings() {
		register_setting( 'gdi_settings', 'gdi_root_folder_id', array(
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => ''
		) );

		register_setting( 'gdi_settings', 'gdi_service_account_json', array(
			'sanitize_callback' => array( $this, 'sanitize_json' ),
			'default'           => ''
		) );

		register_setting( 'gdi_settings', 'gdi_cache_duration', array(
			'sanitize_callback' => 'absint',
			'default'           => 3600 // 1 hour
		) );

		add_settings_section(
			'gdi_settings_section',
			__( 'Google Drive Settings', 'google-drive-integration' ),
			array( $this, 'settings_section_callback' ),
			'gdi_settings'
		);

		add_settings_field(
			'gdi_service_account_json',
			__( 'Service Account JSON', 'google-drive-integration' ),
			array( $this, 'service_account_json_callback' ),
			'gdi_settings',
			'gdi_settings_section'
		);

		add_settings_field(
			'gdi_root_folder_id',
			__( 'Root Folder ID', 'google-drive-integration' ),
			array( $this, 'root_folder_id_callback' ),
			'gdi_settings',
			'gdi_settings_section'
		);

		add_settings_field(
			'gdi_cache_duration',
			__( 'Cache Duration (seconds)', 'google-drive-integration' ),
			array( $this, 'cache_duration_callback' ),
			'gdi_settings',
			'gdi_settings_section'
		);
	}

	/**
	 * Settings section callback
	 */
	public function settings_section_callback() {
		echo '<p>' . __( 'Configure your Google Drive integration settings.', 'google-drive-integration' ) . '</p>';
	}

	/**
	 * Service account JSON callback
	 */
	public function service_account_json_callback() {
		$service_account_json = get_option( 'gdi_service_account_json' );
		?>
        <textarea id="gdi_service_account_json" name="gdi_service_account_json" rows="10" cols="50"
                  class="large-text code"><?php echo esc_textarea( $service_account_json ); ?></textarea>
        <p class="description">
			<?php _e( 'Paste your Google Service Account JSON credentials here. You can create a service account in the Google Cloud Console.', 'google-drive-integration' ); ?>
        </p>
        <button type="button" id="gdi-test-connection" class="button button-secondary">
			<?php _e( 'Test Connection', 'google-drive-integration' ); ?>
        </button>
        <span id="gdi-connection-status"></span>
		<?php
	}

	/**
	 * Root folder ID callback
	 */
	public function root_folder_id_callback() {
		$root_folder_id = get_option( 'gdi_root_folder_id' );
		?>
        <input type="text" id="gdi_root_folder_id" name="gdi_root_folder_id"
               value="<?php echo esc_attr( $root_folder_id ); ?>" class="regular-text">
        <p class="description">
			<?php _e( 'Enter the Google Drive folder ID to use as the root folder for this integration. You can find this in the URL when viewing the folder in Google Drive.', 'google-drive-integration' ); ?>
        </p>
		<?php
	}

	/**
	 * Cache duration callback
	 */
	public function cache_duration_callback() {
		$cache_duration = get_option( 'gdi_cache_duration', 3600 );
		?>
        <input type="number" id="gdi_cache_duration" name="gdi_cache_duration"
               value="<?php echo esc_attr( $cache_duration ); ?>" min="0" step="1" class="small-text">
        <p class="description">
			<?php _e( 'Duration in seconds to cache Google Drive API responses. Set to 0 to disable caching.', 'google-drive-integration' ); ?>
        </p>
		<?php
	}

	/**
	 * Sanitize JSON input
	 *
	 * @param string $input The JSON string to sanitize
	 *
	 * @return string Sanitized JSON
	 */
	public function sanitize_json( $input ) {
		if ( empty( $input ) ) {
			return '';
		}

		// Try to decode the JSON to validate it
		$decoded = json_decode( $input, true );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			add_settings_error(
				'gdi_service_account_json',
				'gdi_invalid_json',
				__( 'Invalid JSON format for Service Account credentials.', 'google-drive-integration' ),
				'error'
			);

			return get_option( 'gdi_service_account_json', '' ); // Return previous value
		}

		// Check for required fields
		if ( ! isset( $decoded['client_email'] ) || ! isset( $decoded['private_key'] ) ) {
			add_settings_error(
				'gdi_service_account_json',
				'gdi_invalid_credentials',
				__( 'Service Account JSON is missing required fields (client_email or private_key).', 'google-drive-integration' ),
				'error'
			);

			return get_option( 'gdi_service_account_json', '' ); // Return previous value
		}

		return $input;
	}

	/**
	 * Display settings page
	 */
	public function display_settings_page() {
		// Check user capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            <form action="options.php" method="post">
				<?php
				settings_fields( 'gdi_settings' );
				do_settings_sections( 'gdi_settings' );
				submit_button();
				?>
            </form>
        </div>
		<?php
	}

	/**
	 * Display admin page
	 */
	public function display_admin_page() {
		// Check user capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Check if credentials are configured
		$service_account_json = get_option( 'gdi_service_account_json', '' );
		$root_folder_id       = get_option( 'gdi_root_folder_id', '' );

		if ( empty( $service_account_json ) || empty( $root_folder_id ) ) {
			?>
            <div class="wrap">
                <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
                <div class="notice notice-warning">
                    <p><?php _e( 'Google Drive Integration is not configured. Please go to the settings page to configure your service account credentials and root folder ID.', 'google-drive-integration' ); ?></p>
                    <p><a href="<?php echo admin_url( 'admin.php?page=google-drive-integration-settings' ); ?>"
                          class="button button-primary"><?php _e( 'Go to Settings', 'google-drive-integration' ); ?></a>
                    </p>
                </div>
            </div>
			<?php
			return;
		}

		// Get current folder contents
		$current_folder_id = isset( $_GET['folder_id'] ) ? sanitize_text_field( $_GET['folder_id'] ) : $root_folder_id;

		try {
			// Get folder contents
			$contents = $this->google_drive_service->getFolderContents( $current_folder_id );

			// Display folder contents
			?>
            <div class="wrap">
                <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

                <div class="gdi-navigation">
					<?php if ( $current_folder_id !== $root_folder_id ): ?>
                        <a href="<?php echo admin_url( 'admin.php?page=google-drive-integration' ); ?>" class="button">
							<?php _e( 'Back to Root', 'google-drive-integration' ); ?>
                        </a>
					<?php endif; ?>
                </div>

                <div class="gdi-file-browser">
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                        <tr>
                            <th><?php _e( 'Name', 'google-drive-integration' ); ?></th>
                            <th><?php _e( 'Type', 'google-drive-integration' ); ?></th>
                            <th><?php _e( 'Modified', 'google-drive-integration' ); ?></th>
                            <th><?php _e( 'Actions', 'google-drive-integration' ); ?></th>
                        </tr>
                        </thead>
                        <tbody id="gdi-file-list">
						<?php if ( empty( $contents ) ): ?>
                            <tr>
                                <td colspan="4"><?php _e( 'No files found in this folder.', 'google-drive-integration' ); ?></td>
                            </tr>
						<?php else: ?>
							<?php foreach ( $contents as $item ): ?>
                                <tr>
                                    <td>
										<?php if ( $item['type'] === 'folder' ): ?>
                                            <a href="<?php echo admin_url( 'admin.php?page=google-drive-integration&folder_id=' . $item['id'] ); ?>">
                                                <span class="dashicons dashicons-category"></span> <?php echo esc_html( $item['name'] ); ?>
                                            </a>
										<?php else: ?>
                                            <span class="dashicons dashicons-media-default"></span> <?php echo esc_html( $item['name'] ); ?>
										<?php endif; ?>
                                    </td>
                                    <td><?php echo esc_html( $item['type'] ); ?></td>
                                    <td><?php echo isset( $item['modifiedTime'] ) ? esc_html( $item['modifiedTime'] ) : ''; ?></td>
                                    <td>
										<?php if ( $item['type'] !== 'folder' ): ?>
                                            <a href="<?php echo esc_url( $item['webViewLink'] ); ?>" target="_blank"
                                               class="button button-small">
												<?php _e( 'View', 'google-drive-integration' ); ?>
                                            </a>
										<?php endif; ?>
                                    </td>
                                </tr>
							<?php endforeach; ?>
						<?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
			<?php
		} catch ( Exception $e ) {
			?>
            <div class="wrap">
                <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
                <div class="notice notice-error">
                    <p><?php echo esc_html( $e->getMessage() ); ?></p>
                </div>
            </div>
			<?php
		}
	}

	/**
	 * Enqueue admin assets
	 *
	 * @param string $hook The current admin page
	 */
	public function enqueue_admin_assets( $hook ) {
		if ( ! in_array( $hook, array(
			'toplevel_page_google-drive-integration',
			'google-drive_page_google-drive-integration-settings'
		) ) ) {
			return;
		}

		wp_enqueue_style(
			'gdi-admin-styles',
			GDI_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			GDI_PLUGIN_VERSION
		);

		wp_enqueue_script(
			'gdi-admin-scripts',
			GDI_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			GDI_PLUGIN_VERSION,
			true
		);

		wp_localize_script( 'gdi-admin-scripts', 'gdiData', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'gdi_nonce' )
		) );
	}

	/**
	 * AJAX handler for changing folders
	 */
	public function ajax_change_folder() {
		// Check nonce
		check_ajax_referer( 'gdi_nonce', 'nonce' );

		// Check user capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Insufficient permissions' );
		}

		$folder_id = isset( $_POST['folder_id'] ) ? sanitize_text_field( $_POST['folder_id'] ) : '';

		if ( empty( $folder_id ) ) {
			wp_send_json_error( 'Invalid folder ID' );
		}

		try {
			$contents = $this->google_drive_service->getFolderContents( $folder_id );
			wp_send_json_success( $contents );
		} catch ( Exception $e ) {
			wp_send_json_error( $e->getMessage() );
		}
	}

	/**
	 * AJAX handler for testing connection
	 */
	public function ajax_test_connection() {
		// Check nonce
		check_ajax_referer( 'gdi_nonce', 'nonce' );

		// Check user capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Insufficient permissions' );
		}

		$service_account_json = isset( $_POST['credentials'] ) ? $_POST['credentials'] : '';

		if ( empty( $service_account_json ) ) {
			wp_send_json_error( 'Service account credentials are required' );
		}

		try {
			// Create a temporary service
			$temp_service = new GoogleDriveService( $service_account_json, $this->cache_service );

			// Test the connection by trying to access the drive
			$test_result = $temp_service->testConnection();

			wp_send_json_success( array(
				'message' => __( 'Connection successful! Your credentials are valid.', 'google-drive-integration' )
			) );
		} catch ( Exception $e ) {
			wp_send_json_error( array(
				'message' => $e->getMessage()
			) );
		}
	}
}

// Initialize the plugin
function google_drive_integration_init() {
	Google_Drive_Integration::get_instance();
}

add_action( 'plugins_loaded', 'google_drive_integration_init' );

/**
 * Create necessary directories on plugin activation
 */
function google_drive_integration_activate() {
	// Create cache directory if it doesn't exist
	if ( ! file_exists( GDI_CACHE_DIR ) ) {
		wp_mkdir_p( GDI_CACHE_DIR );
	}

	// Create assets directories
	wp_mkdir_p( GDI_PLUGIN_PATH . 'assets/css' );
	wp_mkdir_p( GDI_PLUGIN_PATH . 'assets/js' );

	// Create basic CSS file
	$css_content = <<<CSS
.gdi-file-browser {
    margin-top: 20px;
}

.gdi-navigation {
    margin: 15px 0;
}

#gdi-connection-status {
    margin-left: 10px;
    line-height: 28px;
}

.gdi-success {
    color: green;
}

.gdi-error {
    color: red;
}
CSS;

	file_put_contents( GDI_PLUGIN_PATH . 'assets/css/admin.css', $css_content );

	// Create basic JS file
	$js_content = <<<JS
jQuery(document).ready(function($) {
    // Test connection button
    $('#gdi-test-connection').on('click', function() {
        var credentials = $('#gdi_service_account_json').val();
        var statusEl = $('#gdi-connection-status');
        
        if (!credentials) {
            statusEl.html('<span class="gdi-error">Please enter service account credentials</span>');
            return;
        }
        
        statusEl.html('<span>Testing connection...</span>');
        
        $.ajax({
            url: gdiData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'gdi_test_connection',
                nonce: gdiData.nonce,
                credentials: credentials
            },
            success: function(response) {
                if (response.success) {
                    statusEl.html('<span class="gdi-success">' + response.data.message + '</span>');
                } else {
                    statusEl.html('<span class="gdi-error">' + response.data.message + '</span>');
                }
            },
            error: function() {
                statusEl.html('<span class="gdi-error">Connection failed. Please check your network.</span>');
            }
        });
    });
});
JS;

	file_put_contents( GDI_PLUGIN_PATH . 'assets/js/admin.js', $js_content );

	// Add index.php to all directories for security
	$dirs = array(
		GDI_CACHE_DIR,
		GDI_PLUGIN_PATH . 'assets/',
		GDI_PLUGIN_PATH . 'assets/css/',
		GDI_PLUGIN_PATH . 'assets/js/'
	);

	foreach ( $dirs as $dir ) {
		if ( ! file_exists( $dir . 'index.php' ) ) {
			file_put_contents( $dir . 'index.php', '<?php // Silence is golden' );
		}
	}
}

register_activation_hook( __FILE__, 'google_drive_integration_activate' );