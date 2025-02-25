<?php
// folder-contents.php
/**
 * Folder contents template
 *
 * @var array $folder_contents
 * @var array $atts
 */

// Get shortcode attributes
$title            = ! empty( $atts['title'] ) ? esc_html( $atts['title'] ) : '';
$show_breadcrumbs = isset( $atts['show_breadcrumbs'] ) ? filter_var( $atts['show_breadcrumbs'], FILTER_VALIDATE_BOOLEAN ) : true;
?>

<div class="wp-gdrive-folder-container" data-folder-id="<?php echo esc_attr( $folder_contents['current']['id'] ); ?>">
	<?php if ( ! empty( $title ) ): ?>
        <h3 class="wp-gdrive-folder-title"><?php echo $title; ?></h3>
	<?php endif; ?>

	<?php if ( $show_breadcrumbs ): ?>
        <div class="wp-gdrive-breadcrumbs">
			<?php
			try {
				$breadcrumbs = $this->driveService->getBreadcrumbs();

				if ( ! empty( $breadcrumbs ) ) {
					foreach ( $breadcrumbs as $index => $crumb ) {
						if ( $index > 0 ) {
							echo '<span class="wp-gdrive-breadcrumb-separator">/</span>';
						}

						// Only make parent folders clickable
						if ( $index < count( $breadcrumbs ) - 1 ) {
							echo '<a href="#" class="wp-gdrive-folder-link" data-folder-id="' . esc_attr( $crumb['id'] ) . '">';
							echo esc_html( $crumb['name'] );
							echo '</a>';
						} else {
							echo '<span class="wp-gdrive-current-folder">' . esc_html( $crumb['name'] ) . '</span>';
						}
					}
				} else {
					// Fallback to parent/current if breadcrumbs not available
					if ( $folder_contents['parent'] ) {
						echo '<a href="#" class="wp-gdrive-folder-link" data-folder-id="' . esc_attr( $folder_contents['parent']['id'] ) . '">';
						echo '<span class="dashicons dashicons-arrow-left-alt"></span>';
						echo esc_html( $folder_contents['parent']['name'] );
						echo '</a>';
						echo '<span class="wp-gdrive-breadcrumb-separator">/</span>';
					}
					echo '<span class="wp-gdrive-current-folder">' . esc_html( $folder_contents['current']['name'] ) . '</span>';
				}
			} catch ( Exception $e ) {
				// Fallback if breadcrumbs fail
				echo '<span class="wp-gdrive-current-folder">' . esc_html( $folder_contents['current']['name'] ) . '</span>';
			}
			?>
        </div>
	<?php endif; ?>

    <div class="wp-gdrive-items">
		<?php if ( empty( $folder_contents['items'] ) ): ?>
            <p class="wp-gdrive-empty-folder"><?php _e( 'This folder is empty.', 'wp-google-drive' ); ?></p>
		<?php else: ?>
            <div class="wp-gdrive-grid">
				<?php foreach ( $folder_contents['items'] as $item ): ?>
                    <div class="wp-gdrive-item <?php echo $item['type']; ?>">
						<?php if ( $item['type'] === 'folder' ): ?>
                            <a href="#" class="wp-gdrive-folder-link"
                               data-folder-id="<?php echo esc_attr( $item['id'] ); ?>">
                                <div class="wp-gdrive-item-icon">
                                    <span class="dashicons dashicons-category"></span>
                                </div>
                                <div class="wp-gdrive-item-name"><?php echo esc_html( $item['name'] ); ?></div>
                            </a>
						<?php else: ?>
                            <a href="<?php echo esc_url( $item['webViewLink'] ); ?>" target="_blank"
                               class="wp-gdrive-file-link">
                                <div class="wp-gdrive-item-icon">
									<?php if ( ! empty( $item['thumbnailLink'] ) ): ?>
                                        <img src="<?php echo esc_url( $item['thumbnailLink'] ); ?>"
                                             alt="<?php echo esc_attr( $item['name'] ); ?>">
									<?php else: ?>
                                        <img src="<?php echo esc_url( $item['iconLink'] ); ?>"
                                             alt="<?php echo esc_attr( $item['name'] ); ?>">
									<?php endif; ?>
                                </div>
                                <div class="wp-gdrive-item-name"><?php echo esc_html( $item['name'] ); ?></div>
                                <div class="wp-gdrive-item-info">
									<?php if ( ! empty( $item['size'] ) ): ?>
                                        <span class="wp-gdrive-item-size"><?php echo size_format( $item['size'] ); ?></span>
									<?php endif; ?>
                                    <span class="wp-gdrive-item-date"><?php echo date_i18n( get_option( 'date_format' ), strtotime( $item['modifiedTime'] ) ); ?></span>
                                </div>
                            </a>
						<?php endif; ?>
                    </div>
				<?php endforeach; ?>
            </div>
		<?php endif; ?>
    </div>
</div>