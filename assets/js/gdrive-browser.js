jQuery(document).ready(function ($) {
    // Handle folder click
    $(document).on('click', '.gdrive-folder-link, .gdrive-open-folder', function (e) {
        e.preventDefault();

        const container = $(this).closest('.gdrive-browser-container');
        const folderId = $(this).data('folder-id');

        changeFolder(container, folderId);
    });

    // Handle breadcrumb click
    $(document).on('click', '.gdrive-breadcrumb-link', function (e) {
        e.preventDefault();

        const container = $(this).closest('.gdrive-browser-container');
        const folderId = $(this).data('folder-id');

        changeFolder(container, folderId);
    });

    // Handle search input
    $(document).on('input', '.gdrive-search-input', function () {
        const container = $(this).closest('.gdrive-browser-container');
        const searchTerm = $(this).val().toLowerCase();

        if (searchTerm.length === 0) {
            // Show all items if search is cleared
            container.find('.gdrive-file-row').show();
            return;
        }

        // Filter items based on search term
        container.find('.gdrive-file-row').each(function () {
            const fileName = $(this).find('.gdrive-file-name').text().toLowerCase();
            if (fileName.includes(searchTerm)) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
    });

    /**
     * Change the current folder
     *
     * @param {jQuery} container The container element
     * @param {string} folderId The folder ID to change to
     */
    function changeFolder(container, folderId) {
        // Show loading indicator
        container.find('.gdrive-loading').addClass('active');
        container.find('.gdrive-files-container').addClass('loading');

        // Clear search input
        container.find('.gdrive-search-input').val('');

        // Make AJAX request
        $.ajax({
            url: gdriveData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'gdrive_change_folder',
                nonce: gdriveData.nonce,
                folder_id: folderId
            },
            success: function (response) {
                if (response.success) {
                    // Update current folder
                    container.attr('data-current-folder', response.data.current_folder);

                    // Update breadcrumbs
                    updateBreadcrumbs(container, response.data.breadcrumbs);

                    // Update files list
                    updateFilesList(container, response.data.files);
                } else {
                    showError(container, response.data.message);
                }
            },
            error: function () {
                showError(container, 'Error connecting to server');
            },
            complete: function () {
                // Hide loading indicator
                container.find('.gdrive-loading').removeClass('active');
                container.find('.gdrive-files-container').removeClass('loading');
            }
        });
    }

    /**
     * Update the breadcrumbs display
     *
     * @param {jQuery} container The container element
     * @param {Array} breadcrumbs The breadcrumbs data
     */
    function updateBreadcrumbs(container, breadcrumbs) {
        const breadcrumbsContainer = container.find('.gdrive-breadcrumbs');

        if (breadcrumbsContainer.length === 0 || !breadcrumbs) {
            return;
        }

        breadcrumbsContainer.empty();

        const last = breadcrumbs.length - 1;
        $.each(breadcrumbs, function (index, crumb) {
            if (index === last) {
                breadcrumbsContainer.append(
                    $('<span>')
                        .addClass('gdrive-breadcrumb-current')
                        .text(crumb.name)
                );
            } else {
                breadcrumbsContainer.append(
                    $('<a>')
                        .attr('href', '#')
                        .addClass('gdrive-breadcrumb-link')
                        .attr('data-folder-id', crumb.id)
                        .text(crumb.name)
                );

                breadcrumbsContainer.append(
                    $('<span>')
                        .addClass('gdrive-breadcrumb-separator')
                        .text('/')
                );
            }
        });
    }

    /**
     * Format file size in human-readable format
     *
     * @param {number} bytes File size in bytes
     * @return {string} Formatted file size
     */
    function formatFileSize(bytes) {
        if (bytes === 0 || bytes === null || bytes === undefined) {
            return '-';
        }

        const units = ['B', 'KB', 'MB', 'GB', 'TB'];
        const i = Math.floor(Math.log(bytes) / Math.log(1024));

        return (bytes / Math.pow(1024, i)).toFixed(2) + ' ' + units[i];
    }

    /**
     * Format date string
     *
     * @param {string} dateString Date string
     * @return {string} Formatted date
     */
    function formatDate(dateString) {
        if (!dateString) {
            return '-';
        }

        const date = new Date(dateString);
        return date.toLocaleDateString();
    }

    /**
     * Get file type from MIME type
     *
     * @param {string} mimeType MIME type
     * @return {string} File type
     */
    function getFileType(mimeType) {
        const types = {
            'application/vnd.google-apps.folder': 'folder',
            'application/vnd.google-apps.document': 'document',
            'application/vnd.google-apps.spreadsheet': 'spreadsheet',
            'application/vnd.google-apps.presentation': 'presentation',
            'application/pdf': 'pdf',
            'image/jpeg': 'image',
            'image/png': 'image',
            'image/gif': 'image',
            'text/plain': 'text',
            'text/csv': 'spreadsheet',
            'application/zip': 'archive',
            'video/mp4': 'video',
            'audio/mpeg': 'audio'
        };

        return types[mimeType] || 'generic';
    }

    /**
     * Get file icon class based on file type
     *
     * @param {string} fileType File type
     * @return {string} CSS class for icon
     */
    function getFileIconClass(fileType) {
        const iconClasses = {
            'folder': 'gdrive-icon-folder',
            'document': 'gdrive-icon-document',
            'spreadsheet': 'gdrive-icon-spreadsheet',
            'presentation': 'gdrive-icon-presentation',
            'pdf': 'gdrive-icon-pdf',
            'image': 'gdrive-icon-image',
            'text': 'gdrive-icon-text',
            'archive': 'gdrive-icon-archive',
            'video': 'gdrive-icon-video',
            'audio': 'gdrive-icon-audio',
            'generic': 'gdrive-icon-generic'
        };

        return iconClasses[fileType] || 'gdrive-icon-generic';
    }

    /**
     * Update the files list
     *
     * @param {jQuery} container The container element
     * @param {Array} files The files data
     */
    function updateFilesList(container, files) {
        const filesContainer = container.find('.gdrive-files-container');


// For empty folder display:
        if (!files || files.length === 0) {
            filesContainer.html(
                $('<div>')
                    .addClass('gdrive-empty-folder')
                    .append(
                        $('<i>').addClass('fas fa-folder-open')
                    )
                    .append(
                        $('<p>').text('No files found in this folder.')
                    )
            );
            return;
        }

        // Sort files: folders first, then alphabetically
        files.sort(function (a, b) {
            const aIsFolder = a.mimeType === 'application/vnd.google-apps.folder';
            const bIsFolder = b.mimeType === 'application/vnd.google-apps.folder';

            if (aIsFolder && !bIsFolder) return -1;
            if (!aIsFolder && bIsFolder) return 1;

            return a.name.localeCompare(b.name);
        });

        // Create table
        const table = $('<table>').addClass('gdrive-files-table');

        // Add table header
        const thead = $('<thead>').append(
            $('<tr>')
                .append($('<th>').addClass('gdrive-col-name').text('Name'))
                .append($('<th>').addClass('gdrive-col-size').text('Size'))
                .append($('<th>').addClass('gdrive-col-modified').text('Modified'))
                .append($('<th>').addClass('gdrive-col-actions').text('Actions'))
        );

        // Add table body
        const tbody = $('<tbody>');

        // Add files
        $.each(files, function (index, file) {
            const isFolder = file.mimeType === 'application/vnd.google-apps.folder';
            const fileType = getFileType(file.mimeType);
            const iconClass = getFileIconClass(fileType);

            const row = $('<tr>').addClass('gdrive-file-row ' + (isFolder ? 'gdrive-folder-row' : 'gdrive-document-row'));

            // Name cell
            const nameCell = $('<td>').addClass('gdrive-col-name');

            if (isFolder) {
                nameCell.append(
                    $('<a>')
                        .attr('href', '#')
                        .addClass('gdrive-folder-link')
                        .attr('data-folder-id', file.id)
                        .append(
                            $('<span>').addClass('gdrive-icon ' + iconClass)
                        )
                        .append(
                            $('<span>').addClass('gdrive-file-name').text(file.name)
                        )
                );
            } else {
                nameCell.append(
                    $('<a>')
                        .attr('href', file.webViewLink)
                        .attr('target', '_blank')
                        .addClass('gdrive-file-link')
                        .append(
                            $('<span>').addClass('gdrive-icon ' + iconClass)
                        )
                        .append(
                            $('<span>').addClass('gdrive-file-name').text(file.name)
                        )
                );
            }

            // Size cell
            const sizeCell = $('<td>')
                .addClass('gdrive-col-size')
                .text(isFolder ? '-' : formatFileSize(file.size));

            // Modified cell
            const modifiedCell = $('<td>')
                .addClass('gdrive-col-modified')
                .text(formatDate(file.modifiedTime));

            // Actions cell
            const actionsCell = $('<td>').addClass('gdrive-col-actions');

// For action buttons:
            if (isFolder) {
                actionsCell.append(
                    $('<a>')
                        .attr('href', '#')
                        .addClass('gdrive-action-button gdrive-open-folder')
                        .attr('data-folder-id', file.id)
                        .attr('title', 'Open folder')
                );
            } else {
                actionsCell.append(
                    $('<a>')
                        .attr('href', file.webViewLink)
                        .attr('target', '_blank')
                        .addClass('gdrive-action-button gdrive-view-file')
                        .attr('title', 'View file')
                );
            }


            // Add cells to row
            row.append(nameCell).append(sizeCell).append(modifiedCell).append(actionsCell);

            // Add row to table body
            tbody.append(row);
        });

        // Assemble table
        table.append(thead).append(tbody);

        // Update container
        filesContainer.empty().append(table);
    }

    /**
     * Show an error message
     *
     * @param {jQuery} container The container element
     * @param {string} message The error message
     */
    function showError(container, message) {
        container.find('.gdrive-files-container').html(
            $('<div>')
                .addClass('gdrive-error')
                .text(message)
        );
    }
});