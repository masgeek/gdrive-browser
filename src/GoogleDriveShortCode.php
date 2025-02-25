<?php

namespace App;

use Psr\Cache\InvalidArgumentException;

/**
 * Google Drive Shortcode Handler
 */
class GoogleDriveShortcode {

	/**
	 * @var GoogleDriveService
	 */
	private GoogleDriveService $driveService;

	/**
	 * Constructor
	 */
	public function __construct() {
		add_shortcode( 'gdrive_browser', [ $this, 'render_drive_browser' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'register_scripts' ] );
		add_action( 'wp_ajax_gdrive_change_folder', [ $this, 'ajax_change_folder' ] );
		add_action( 'wp_ajax_nopriv_gdrive_change_folder', [ $this, 'ajax_change_folder' ] );
	}

	/**
	 * Register scripts and styles
	 */
	public function register_scripts(): void {
		wp_register_style(
			'gdrive-browser-style',
			GDI_PLUGIN_URL . 'assets/css/gdrive-browser.css',
			[],
			GDI_PLUGIN_VERSION
		);

		wp_register_script(
			'gdrive-browser-script',
			GDI_PLUGIN_URL . 'assets/js/gdrive-browser.js',
			[ 'jquery' ],
			GDI_PLUGIN_VERSION,
			true
		);

		wp_localize_script( 'gdrive-browser-script', 'gdriveData', [
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'gdrive_nonce' )
		] );
	}

	/**
	 * Render Google Drive browser
	 *
	 * @param array $shortCodeAttr Shortcode attributes
	 *
	 * @return string HTML output
	 * @throws InvalidArgumentException
	 */
	public function render_drive_browser( array $shortCodeAttr = [] ): string {
		// Enqueue required assets
		wp_enqueue_style( 'gdrive-browser-style' );
		wp_enqueue_script( 'gdrive-browser-script' );

		// Process attributes
		$attributes = shortcode_atts( [
			'folder_id'        => get_option( 'gdi_root_folder_id' ),
			'title'            => __( 'Google Drive Files', 'google-drive-integration' ),
			'show_breadcrumbs' => true,
		], $shortCodeAttr );

		// Initialize drive service
		try {
			$this->driveService = new GoogleDriveService( $attributes['folder_id'] );
			$files              = $this->driveService->getFolderContents();
			$breadcrumbs        = ( $attributes['show_breadcrumbs'] ) ? $this->driveService->getBreadcrumbs() : [];

			// Start output buffering
			ob_start();

			// Container
			echo '<div class="gdrive-browser-container" data-current-folder="' . esc_attr( $attributes['folder_id'] ) . '">';

			// Title
			echo '<h3 class="gdrive-browser-title">' . esc_html( $attributes['title'] ) . '</h3>';

			// Breadcrumbs
			if ( $attributes['show_breadcrumbs'] && ! empty( $breadcrumbs ) ) {
				echo '<div class="gdrive-breadcrumbs">';
				$last = count( $breadcrumbs ) - 1;
				foreach ( $breadcrumbs as $index => $crumb ) {
					if ( $index === $last ) {
						echo '<span class="gdrive-breadcrumb-current">' . esc_html( $crumb['name'] ) . '</span>';
					} else {
						echo '<a href="#" class="gdrive-breadcrumb-link" data-folder-id="' . esc_attr( $crumb['id'] ) . '">' . esc_html( $crumb['name'] ) . '</a>';
						echo '<span class="gdrive-breadcrumb-separator"> &gt; </span>';
					}
				}
				echo '</div>';
			}

			// Loading indicator
			echo '<div class="gdrive-loading" style="display: none;">' . __( 'Loading...', 'google-drive-integration' ) . '</div>';

			// Files list
			echo '<div class="gdrive-files-container">';
			if ( empty( $files ) ) {
				echo '<p class="gdrive-empty-folder">' . __( 'No files found in this folder.', 'google-drive-integration' ) . '</p>';
			} else {
				echo '<ul class="gdrive-files-list">';

				// Sort files: folders first, then alphabetically
				usort( $files, function ( $a, $b ) {
					$aIsFolder = $a->getMimeType() === 'application/vnd.google-apps.folder';
					$bIsFolder = $b->getMimeType() === 'application/vnd.google-apps.folder';

					if ( $aIsFolder && ! $bIsFolder ) {
						return - 1;
					}
					if ( ! $aIsFolder && $bIsFolder ) {
						return 1;
					}

					return strcmp( $a->getName(), $b->getName() );
				} );

				foreach ( $files as $file ) {
					$isFolder = $file->getMimeType() === 'application/vnd.google-apps.folder';
					$fileId   = $file->getId();
					$fileName = $file->getName();
					$fileLink = $file->getWebViewLink();

					echo '<li class="gdrive-file-item ' . ( $isFolder ? 'gdrive-folder' : 'gdrive-file' ) . '">';

					if ( $isFolder ) {
						echo '<a href="#" class="gdrive-folder-link" data-folder-id="' . esc_attr( $fileId ) . '">';
						echo '<span class="gdrive-folder-icon"></span>';
						echo esc_html( $fileName );
						echo '</a>';
					} else {
						echo '<a href="' . esc_url( $fileLink ) . '" target="_blank" class="gdrive-file-link">';
						echo '<span class="gdrive-file-icon"></span>';
						echo esc_html( $fileName );
						echo '</a>';
					}

					echo '</li>';
				}

				echo '</ul>';
			}
			echo '</div>'; // End files container

			echo '</div>'; // End main container

			return ob_get_clean();

		} catch ( \Exception $e ) {
			return '<div class="gdrive-error">' . __( 'Error: Unable to access Google Drive files. '.$e->getMessage(), 'google-drive-integration' ) . '</div>';
		}
	}

	/**
	 * AJAX handler for changing folders
	 */
	public function ajax_change_folder(): void {
		// Verify nonce
		check_ajax_referer( 'gdrive_nonce', 'nonce' );

		$folder_id = isset( $_POST['folder_id'] ) ? sanitize_text_field( $_POST['folder_id'] ) : '';

		if ( empty( $folder_id ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid folder ID', 'google-drive-integration' ) ] );

			return;
		}

		try {
			$this->driveService = new GoogleDriveService();
			$this->driveService->changeFolder($folder_id);

			$files       = $this->driveService->getFolderContents();
			$breadcrumbs = $this->driveService->getBreadcrumbs();

			wp_send_json_success( [
				'files'          => $files,
				'breadcrumbs'    => $breadcrumbs,
				'current_folder' => $folder_id
			] );

		} catch ( \Exception $e ) {
			wp_send_json_error( [
				'message' => __( 'Error accessing Google Drive folder', 'google-drive-integration' ),
				'error'   => $e->getMessage()
			] );
		}
	}
}