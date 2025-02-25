<?php
/**
 * Plugin Name: Google Drive Settings
 * Description: A plugin to set Google Drive credentials.
 * Version: 1.0
 * Author: Your Name
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Add settings menu
function gdrive_add_settings_menu() {
	add_menu_page( 'Google Drive Settings', 'Google Drive', 'manage_options', 'gdrive-settings', 'gdrive_render_settings_page' );
}

add_action( 'admin_menu', 'gdrive_add_settings_menu' );

// Register settings
function gdrive_register_settings() {
	register_setting( 'gdrive_settings_group', 'gdrive_folder_id' );
	register_setting( 'gdrive_settings_group', 'gdrive_service_account_email' );
	register_setting( 'gdrive_settings_group', 'gdrive_cache_duration' );
}

add_action( 'admin_init', 'gdrive_register_settings' );

// Render settings page
function gdrive_render_settings_page() {
	?>
    <div class="wrap">
        <h1>Google Drive Settings</h1>
        <form method="post" action="options.php" enctype="multipart/form-data">
			<?php settings_fields( 'gdrive_settings_group' ); ?>
			<?php do_settings_sections( 'gdrive_settings_group' ); ?>

            <table class="form-table">
                <tr>
                    <th><label for="gdrive_folder_id">Folder ID</label></th>
                    <td><input type="text" name="gdrive_folder_id"
                               value="<?php echo esc_attr( get_option( 'gdrive_folder_id' ) ); ?>"
                               class="regular-text"/></td>
                </tr>
                <tr>
                    <th><label for="gdrive_service_account_email">Service Account Email</label></th>
                    <td><input type="email" name="gdrive_service_account_email"
                               value="<?php echo esc_attr( get_option( 'gdrive_service_account_email' ) ); ?>"
                               class="regular-text"/></td>
                </tr>
                <tr>
                    <th><label for="gdrive_cache_duration">Cache Duration (minutes)</label></th>
                    <td><input type="number" name="gdrive_cache_duration"
                               value="<?php echo esc_attr( get_option( 'gdrive_cache_duration' ) ); ?>"
                               class="small-text"/></td>
                </tr>
                <tr>
                    <th><label for="gdrive_service_account_json">Service Account JSON File</label></th>
                    <td>
                        <input type="file" name="gdrive_service_account_json" accept="application/json"/>
						<?php if ( get_option( 'gdrive_service_account_json' ) ): ?>
                            <p>File uploaded successfully.</p>
						<?php endif; ?>
                    </td>
                </tr>
            </table>

			<?php submit_button(); ?>
        </form>
    </div>
	<?php
}

// Handle file upload securely
function gdrive_handle_file_upload() {
	if ( ! empty( $_FILES['gdrive_service_account_json']['tmp_name'] ) ) {
		$uploads_dir = ABSPATH . 'wp-content/private/';
		if ( ! file_exists( $uploads_dir ) ) {
			mkdir( $uploads_dir, 0755, true );
		}
		$target_file = $uploads_dir . 'gdrive_service_account.json';
		if ( move_uploaded_file( $_FILES['gdrive_service_account_json']['tmp_name'], $target_file ) ) {
			update_option( 'gdrive_service_account_json', $target_file );
		}
	}
}

add_action( 'admin_init', 'gdrive_handle_file_upload' );

// Get JSON file path securely
function gdrive_get_json_file() {
	return defined( 'GDRIVE_SERVICE_ACCOUNT_JSON' ) ? GDRIVE_SERVICE_ACCOUNT_JSON : get_option( 'gdrive_service_account_json' );
}
