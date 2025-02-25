jQuery(document).ready(function ($) {
    // Handle folder click
    $(document).on('click', '.gdrive-folder-link', function (e) {
        e.preventDefault();

        var container = $(this).closest('.gdrive-browser-container');
        var folderId = $(this).data('folder-id');

        changeFolder(container, folderId);
    });

    // Handle breadcrumb click
    $(document).on('click', '.gdrive-breadcrumb-link', function (e) {
        e.preventDefault();

        var container = $(this).closest('.gdrive-browser-container');
        var folderId = $(this).data('folder-id');

        changeFolder(container, folderId);
    });

    /**
     * Change the current folder
     *
     * @param {jQuery} container The container element
     * @param {string} folderId The folder ID to change to
     */
    function changeFolder(container, folderId) {
        // Show loading indicator
        container.find('.gdrive-loading').show();
        container.find('.gdrive-files-container').addClass('gdrive-loading-active');

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
                container.find('.gdrive-loading').hide();
                container.find('.gdrive-files-container').removeClass('gdrive-loading-active');
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
        var breadcrumbsContainer = container.find('.gdrive-breadcrumbs');

        if (breadcrumbsContainer.length === 0 || !breadcrumbs || breadcrumbs.length === 0) {
            return;
        }

        breadcrumbsContainer.empty();

        var last = breadcrumbs.length - 1;
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
                        .html(' &gt; ')
                );
            }
        });
    }

    /**
     * Update the files list
     *
     * @param {jQuery} container The container element
     * @param {Array} files The files data
     */
    function updateFilesList(container, files) {
        var filesContainer = container.find('.gdrive-files-container');

        if (files.length === 0) {
            filesContainer.html('<p class="gdrive-empty-folder">No files found in this folder.</p>');
            return;
        }

        // Sort files: folders first, then alphabetically
        files.sort(function (a, b) {
            var aIsFolder = a.mimeType === 'application/vnd.google-apps.folder';
            var bIsFolder = b.mimeType === 'application/vnd.google-apps.folder';

            if (aIsFolder && !bIsFolder) return -1;
            if (!aIsFolder && bIsFolder) return 1;

            return a.name.localeCompare(b.name);
        });

        var filesList = $('<ul>').addClass('gdrive-files-list');

        $.each(files, function (index, file) {
            var isFolder = file.mimeType === 'application/vnd.google-apps.folder';
            var fileItem = $('<li>').addClass('gdrive-file-item');

            if (isFolder) {
                fileItem.addClass('gdrive-folder');

                var folderLink = $('<a>')
                    .attr('href', '#')
                    .addClass('gdrive-folder-link')
                    .attr('data-folder-id', file.id);

                folderLink.append($('<span>').addClass('gdrive-folder-icon'));
                folderLink.append(document.createTextNode(file.name));

                fileItem.append(folderLink);
            } else {
                fileItem.addClass('gdrive-file');

                var fileLink = $('<a>')
                    .attr('href', file.webViewLink)
                    .attr('target', '_blank')
                    .addClass('gdrive-file-link');

                fileLink.append($('<span>').addClass('gdrive-file-icon'));
                fileLink.append(document.createTextNode(file.name));

                fileItem.append(fileLink);
            }

            filesList.append(fileItem);
        });

        filesContainer.empty().append(filesList);
    }

    /**
     * Show an error message
     *
     * @param {jQuery} container The container element
     * @param {string} message The error message
     */
    function showError(container, message) {
        var filesContainer = container.find('.gdrive-files-container');
        filesContainer.html('<div class="gdrive-error">' + message + '</div>');
    }
});