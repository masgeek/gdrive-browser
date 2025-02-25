<?php
// admin-settings.php
/**
 * Admin settings page template
 *
 * @var bool $credentials_exist
 * @var bool $is_authenticated
 * @var \WP_GDrive\GoogleDriveService $driveService
 */
?>
<div class="wrap">
    <h1><?php _e( 'Google Drive Integration Settings', 'wp-google-drive' ); ?></h1>

    <div class="wp-gdrive-admin-container">
        <div class="wp-gdrive-admin-section">
            <h2><?php _e( 'Authentication Status', 'wp-google-drive' ); ?></h2>

			<?php if ( ! $credentials_exist ): ?>
                <div class="notice notice-error">
                    <p>
						<?php _e( 'Google API credentials file not found. Please upload your credentials.json file to the plugin directory.', 'wp-google-drive' ); ?>
                    </p>
                    <p>
						<?php _e( 'You can obtain your credentials file from the Google Cloud Console:', 'wp-google-drive' ); ?>
                        <a href="https://console.cloud.google.com/apis/credentials" target="_blank">https://console.cloud.google.com/apis/credentials</a>
                    </p>
                </div>
			<?php elseif ( ! $is_authenticated ): ?>
                <div class="notice notice-warning">
                    <p>
						<?php _e( 'Google Drive API is not authenticated. Please click the button below to authenticate.', 'wp-google-drive' ); ?>
                    </p>
                    <p>
                        <a href="<?php echo esc_url( $this->driveService->getAuthUrl() ); ?>"
                           class="button button-primary">
							<?php _e( 'Authenticate with Google', 'wp-google-drive' ); ?>
                        </a>
                    </p>
                </div>
			<?php else: ?>
                <div class="notice notice-success">
                    <p>
						<?php _e( 'Google Drive API is authenticated and ready to use.', 'wp-google-drive' ); ?>
                    </p>
                    <form method="post" action="">
						<?php wp_nonce_field( 'wp-gdrive-revoke', 'wp_gdrive_nonce' ); ?>
                        <input type="hidden" name="action" value="revoke_token">
                        <button type="submit" class="button">
							<?php _e( 'Revoke Access', 'wp-google-drive' ); ?>
                        </button>
                    </form>
                </div>
			<?php endif; ?>
        </div>

		<?php if ( $is_authenticated ): ?>
            <div class="wp-gdrive-admin-section">
                <h2><?php _e( 'Shortcode Usage', 'wp-google-drive' ); ?></h2>
                <p>
					<?php _e( 'Use the following shortcode to display a Google Drive folder on your site:', 'wp-google-drive' ); ?>
                </p>
                <code>[wp_gdrive_folder folder_id="YOUR_FOLDER_ID" title="Optional Title"
                    show_breadcrumbs="true"]</code>

                <h3><?php _e( 'Parameters', 'wp-google-drive' ); ?></h3>
                <ul>
                    <li><strong>folder_id</strong>
                        - <?php _e( 'The ID of the Google Drive folder (required)', 'wp-google-drive' ); ?></li>
                    <li><strong>title</strong>
                        - <?php _e( 'Optional title to display above the folder contents', 'wp-google-drive' ); ?></li>
                    <li><strong>show_breadcrumbs</strong>
                        - <?php _e( 'Whether to show navigation breadcrumbs (true/false, default: true)', 'wp-google-drive' ); ?>
                    </li>
                </ul>
            </div>

            <div class="wp-gdrive-admin-section">
                <h2><?php _e( 'Settings', 'wp-google-drive' ); ?></h2>
                <form method="post" action="options.php">
					<?php
					settings_fields( 'wp_gdrive_settings' );
					do_settings_sections( 'wp_gdrive_settings' );
					?>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label
                                        for="wp_gdrive_app_name"><?php _e( 'Application Name', 'wp-google-drive' ); ?></label>
                            </th>
                            <td>
                                <input type="text" id="wp_gdrive_app_name" name="wp_gdrive_app_name"
                                       value="<?php echo esc_attr( get_option( 'wp_gdrive_app_name', 'WordPress Google Drive Integration' ) ); ?>"
                                       class="regular-text">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label
                                        for="wp_gdrive_default_folder_id"><?php _e( 'Default Folder ID', 'wp-google-drive' ); ?></label>
                            </th>
                            <td>
                                <input type="text" id="wp_gdrive_default_folder_id" name="wp_gdrive_default_folder_id"
                                       value="<?php echo esc_attr( get_option( 'wp_gdrive_default_folder_id', '' ) ); ?>"
                                       class="regular-text">
                                <p class="description"><?php _e( 'The ID of the Google Drive folder to use by default if none is specified.', 'wp-google-drive' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label
                                        for="wp_gdrive_service_account"><?php _e( 'Service Account Email', 'wp-google-drive' ); ?></label>
                            </th>
                            <td>
                                <input type="email" id="wp_gdrive_service_account" name="wp_gdrive_service_account"
                                       value="<?php echo esc_attr( get_option( 'wp_gdrive_service_account', '' ) ); ?>"
                                       class="regular-text">
                                <p class="description"><?php _e( 'Optional: Email address of the service account to use.', 'wp-google-drive' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label
                                        for="wp_gdrive_cache_lifetime"><?php _e( 'Cache Lifetime (seconds)', 'wp-google-drive' ); ?></label>
                            </th>
                            <td>
                                <input type="number" id="wp_gdrive_cache_lifetime" name="wp_gdrive_cache_lifetime"
                                       value="<?php echo esc_attr( get_option( 'wp_gdrive_cache_lifetime', 3600 ) ); ?>"
                                       min="0" step="1" class="small-text">
                                <p class="description"><?php _e( 'How long to cache Google Drive API responses (in seconds).', 'wp-google-drive' ); ?></p>
                            </td>
                        </tr>
                    </table>
					<?php submit_button(); ?>
                </form>
                <h3><?php _e( 'Cache Management', 'wp-google-drive' ); ?></h3>
                <p>
					<?php _e( 'Google Drive API responses are cached to improve performance. Use the button below to clear the cache.', 'wp-google-drive' ); ?>
                </p>
                <form method="post" action="">
					<?php wp_nonce_field( 'wp-gdrive-clear-cache', 'wp_gdrive_nonce' ); ?>
                    <input type="hidden" name="action" value="clear_cache">
                    <button type="submit" class="button">
						<?php _e( 'Clear Cache', 'wp-google-drive' ); ?>
                    </button>
                </form>
            </div>
		<?php endif; ?>
    </div>
</div>