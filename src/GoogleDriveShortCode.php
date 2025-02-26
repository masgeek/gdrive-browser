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
			'font-awesome',
			'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css',
			[],
			'5.15.4'
		);

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
	public function render_drive_browser( $atts = [] ) {
		// Enqueue required assets
		wp_enqueue_style( 'gdrive-browser-style' );
		wp_enqueue_script( 'gdrive-browser-script' );

		// Process attributes
		$attributes = shortcode_atts( [
			'folder_id'        => get_option( 'gdi_root_folder_id' ),
			'title'            => __( 'Google Drive Files', 'google-drive-integration' ),
			'show_breadcrumbs' => true,
			'items_per_page'   => 20,
			'columns'          => 'name,size,modified,actions',
		], $atts );

		// Initialize drive service
		try {
			$this->driveService = new GoogleDriveService( $attributes['folder_id'] );
			$files              = $this->driveService->getFolderContents();
			$breadcrumbs        = ( $attributes['show_breadcrumbs'] ) ? $this->driveService->getBreadcrumbs() : [];

			// Get selected columns
			$columns = explode( ',', $attributes['columns'] );
			$columns = array_map( 'trim', $columns );

			// Start output buffering
			ob_start();

			// Container
			echo '<div class="gdrive-browser-container" data-current-folder="' . esc_attr( $attributes['folder_id'] ) . '">';

			// Title
			if ( ! empty( $attributes['title'] ) ) {
				echo '<h3 class="gdrive-browser-title">' . esc_html( $attributes['title'] ) . '</h3>';
			}

			// Search box
			echo '<div class="gdrive-search-container">';
			echo '<input type="text" class="gdrive-search-input" placeholder="' . esc_attr__( 'Search files...', 'google-drive-integration' ) . '">';
			echo '</div>';

			// Breadcrumbs
			if ( $attributes['show_breadcrumbs'] && ! empty( $breadcrumbs ) ) {
				echo '<div class="gdrive-breadcrumbs">';

				$last = count( $breadcrumbs ) - 1;
				foreach ( $breadcrumbs as $index => $crumb ) {
					if ( $index === $last ) {
						echo '<span class="gdrive-breadcrumb-current">' . esc_html( $crumb['name'] ) . '</span>';
					} else {
						echo '<a href="#" class="gdrive-breadcrumb-link" data-folder-id="' . esc_attr( $crumb['id'] ) . '">';
						echo esc_html( $crumb['name'] );
						echo '</a>';
						echo '<span class="gdrive-breadcrumb-separator">/</span>';
					}
				}
				echo '</div>';
			}

			// Loading indicator
			echo '<div class="gdrive-loading">';
			echo '<span class="gdrive-spinner"></span>';
			echo '<span>' . __('Loading...', 'google-drive-integration') . '</span>';
			echo '</div>';

			// Files list
			echo '<div class="gdrive-files-container">';

			if ( empty( $files ) ) {
				echo '<div class="gdrive-empty-folder">';
				echo '<i class="fas fa-folder-open"></i>';
				echo '<p>' . __('No files found in this folder.', 'google-drive-integration') . '</p>';
				echo '</div>';
			} else {
				// Sort files: folders first, then alphabetically
				usort( $files, function ( $a, $b ) {
					$aIsFolder = $a->getMimeType() === 'application/vnd.google-apps.folder';
					$bIsFolder = $b->getMimeType() === 'application/vnd.google-apps.folder';

					if ( $aIsFolder && ! $bIsFolder ) {
						return - 1;
					}
					if ( ! $bIsFolder && $aIsFolder ) {
						return 1;
					}

					return strcmp( $a->getName(), $b->getName() );
				} );

				echo '<table class="gdrive-files-table">';

				// Table header
				echo '<thead><tr>';

				if ( in_array( 'name', $columns ) ) {
					echo '<th class="gdrive-col-name">' . __( 'Name', 'google-drive-integration' ) . '</th>';
				}

				if ( in_array( 'size', $columns ) ) {
					echo '<th class="gdrive-col-size">' . __( 'Size', 'google-drive-integration' ) . '</th>';
				}

				if ( in_array( 'modified', $columns ) ) {
					echo '<th class="gdrive-col-modified">' . __( 'Modified', 'google-drive-integration' ) . '</th>';
				}

				if ( in_array( 'actions', $columns ) ) {
					echo '<th class="gdrive-col-actions">' . __( 'Actions', 'google-drive-integration' ) . '</th>';
				}

				echo '</tr></thead>';

				// Table body
				echo '<tbody>';

				foreach ( $files as $file ) {
					$isFolder     = $file->getMimeType() === 'application/vnd.google-apps.folder';
					$fileId       = $file->getId();
					$fileName     = $file->getName();
					$fileLink     = $file->getWebViewLink();
					$fileSize     = $file->getSize() ?? 0;
					$fileModified = $file->getModifiedTime() ?? '';

					// Format file size
					$formattedSize = $isFolder ? '-' : self::format_file_size( $fileSize );

					// Format modified date
					$formattedDate = $fileModified ? date_i18n( get_option( 'date_format' ), strtotime( $fileModified ) ) : '-';

					// Determine file type/icon
					$mimeType  = $file->getMimeType();
					$fileType  = self::get_file_type( $mimeType );
					$iconClass = self::get_file_icon_class( $fileType );

					echo '<tr class="gdrive-file-row ' . ( $isFolder ? 'gdrive-folder-row' : 'gdrive-document-row' ) . '">';

					// Name column
					if ( in_array( 'name', $columns ) ) {
						echo '<td class="gdrive-col-name">';

						if ( $isFolder ) {
							echo '<a href="#" class="gdrive-folder-link" data-folder-id="' . esc_attr( $fileId ) . '">';
							echo '<span class="gdrive-icon ' . esc_attr( $iconClass ) . '"></span>';
							echo '<span class="gdrive-file-name">' . esc_html( $fileName ) . '</span>';
							echo '</a>';
						} else {
							echo '<a href="' . esc_url( $fileLink ) . '" target="_blank" class="gdrive-file-link">';
							echo '<span class="gdrive-icon ' . esc_attr( $iconClass ) . '"></span>';
							echo '<span class="gdrive-file-name">' . esc_html( $fileName ) . '</span>';
							echo '</a>';
						}

						echo '</td>';
					}

					// Size column
					if ( in_array( 'size', $columns ) ) {
						echo '<td class="gdrive-col-size">' . esc_html( $formattedSize ) . '</td>';
					}

					// Modified column
					if ( in_array( 'modified', $columns ) ) {
						echo '<td class="gdrive-col-modified">' . esc_html( $formattedDate ) . '</td>';
					}

					// Actions column
					if ( in_array( 'actions', $columns ) ) {
						echo '<td class="gdrive-col-actions">';

						if ( $isFolder ) {
							echo '<a href="#" class="gdrive-action-button gdrive-open-folder" data-folder-id="' . esc_attr( $fileId ) . '" title="' . esc_attr__( 'Open folder', 'google-drive-integration' ) . '">';
							echo '<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M19 19H5V5h7V3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2v-7h-2v7zM14 3v2h3.6l-9.8 9.8 1.4 1.4L19 6.4V10h2V3h-7z"/></svg>';
							echo '</a>';
						} else {
							echo '<a href="' . esc_url( $fileLink ) . '" target="_blank" class="gdrive-action-button gdrive-view-file" title="' . esc_attr__( 'View file', 'google-drive-integration' ) . '">';
							echo '<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M12 4C7 4 2.73 7.11 1 11.5 2.73 15.89 7 19 12 19s9.27-3.11 11-7.5C21.27 7.11 17 4 12 4zm0 12.5c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/></svg>';
							echo '</a>';
						}

						echo '</td>';
					}

					echo '</tr>';
				}

				echo '</tbody></table>';
			}

			echo '</div>'; // End files container

			// Pagination placeholder
			echo '<div class="gdrive-pagination"></div>';

			echo '</div>'; // End main container

			return ob_get_clean();

		} catch ( \Exception $e ) {
			return '<div class="gdrive-error">' . __( 'Error: Unable to access Google Drive files.', 'google-drive-integration' ) . '</div>';
		}
	}

	/**
	 * Format file size in human-readable format
	 *
	 * @param int $bytes File size in bytes
	 *
	 * @return string Formatted file size
	 */
	private static function format_file_size( $bytes ) {
		if ( $bytes == 0 ) {
			return '0 B';
		}

		$units = [ 'B', 'KB', 'MB', 'GB', 'TB' ];
		$i     = floor( log( $bytes, 1024 ) );

		return round( $bytes / pow( 1024, $i ), 2 ) . ' ' . $units[ $i ];
	}

	/**
	 * Get file type from MIME type
	 *
	 * @param string $mimeType MIME type
	 *
	 * @return string File type
	 */
	private static function get_file_type( $mimeType ) {
		$types = [
			'application/vnd.google-apps.folder'       => 'folder',
			'application/vnd.google-apps.document'     => 'document',
			'application/vnd.google-apps.spreadsheet'  => 'spreadsheet',
			'application/vnd.google-apps.presentation' => 'presentation',
			'application/pdf'                          => 'pdf',
			'image/jpeg'                               => 'image',
			'image/png'                                => 'image',
			'image/gif'                                => 'image',
			'text/plain'                               => 'text',
			'text/csv'                                 => 'spreadsheet',
			'application/zip'                          => 'archive',
			'video/mp4'                                => 'video',
			'audio/mpeg'                               => 'audio',
		];

		return isset( $types[ $mimeType ] ) ? $types[ $mimeType ] : 'generic';
	}

	/**
	 * Get file icon class based on file type
	 *
	 * @param string $fileType File type
	 *
	 * @return string CSS class for icon
	 */
	private static function get_file_icon_class( $fileType ) {
		$iconClasses = [
			'folder'       => 'gdrive-icon-folder',
			'document'     => 'gdrive-icon-document',
			'spreadsheet'  => 'gdrive-icon-spreadsheet',
			'presentation' => 'gdrive-icon-presentation',
			'pdf'          => 'gdrive-icon-pdf',
			'image'        => 'gdrive-icon-image',
			'text'         => 'gdrive-icon-text',
			'archive'      => 'gdrive-icon-archive',
			'video'        => 'gdrive-icon-video',
			'audio'        => 'gdrive-icon-audio',
			'generic'      => 'gdrive-icon-generic',
		];

		return isset( $iconClasses[ $fileType ] ) ? $iconClasses[ $fileType ] : 'gdrive-icon-generic';
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
			$this->driveService->changeFolder( $folder_id );

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