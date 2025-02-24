<?php /** @noinspection PhpUnhandledExceptionInspection */
require_once 'vendor/autoload.php';
session_start();

use App\GoogleDriveService;

$whoops = new \Whoops\Run;
$whoops->pushHandler(new \Whoops\Handler\PrettyPageHandler);
$whoops->register();

// Configuration
$credentialsPath = 'credentials.json';
$appName = 'Google Drive Browser';
$subject = "gdrive@fuelrod-87f7b.iam.gserviceaccount.com";
$defaultFolderId = '13-S74hrLKkT82t3yMaht6OegnC70aBxc';

// Initialize session folder ID if not set
if (!isset($_SESSION['currentFolderId'])) {
    $_SESSION['currentFolderId'] = $defaultFolderId;
}

$driveService = new GoogleDriveService();
$files = $driveService->getFolderContents();
$breadcrumbs = $driveService->getBreadcrumbs();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Google Drive Browser</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
<div class="container mt-5">
    <h1>Google Drive Browser</h1>

    <!-- Breadcrumb Navigation -->
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb" id="breadcrumb-list">
            <?php foreach ($breadcrumbs as $crumb): ?>
                <li class="breadcrumb-item">
                    <button class="btn btn-link breadcrumb-btn"
                            data-folder="<?php echo htmlspecialchars($crumb['id']); ?>">
                        <?php echo htmlspecialchars($crumb['name']); ?>
                    </button>
                </li>
            <?php endforeach; ?>
        </ol>
    </nav>

    <ul class="list-group" id="file-list">
        <?php foreach ($files as $file): ?>
            <li class="list-group-item">
                <?php if ($file->getMimeType() == 'application/vnd.google-apps.folder'): ?>
                    <i class="file-icon fas fa-folder"></i>
                    <button class="btn btn-link folder-btn"
                            data-folder="<?php echo htmlspecialchars($file->getId()); ?>">
                        <?php echo htmlspecialchars($file->getName()); ?>
                    </button>
                <?php else: ?>
                    <i class="file-icon fas fa-file"></i>
                    <a href="<?php echo htmlspecialchars($file->getWebViewLink()); ?>" target="_blank">
                        <?php echo htmlspecialchars($file->getName()); ?>
                    </a>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
    $(document).on('click', '.folder-btn, .breadcrumb-btn', function () {
        const folderId = $(this).data('folder');

        $.ajax({
            url: 'src/change_folder.php',
            type: 'POST',
            data: {folderId: folderId},
            dataType: 'json',
            success: function (data) {
                if (data.status === 'success') {
                    updateFileList(data.files);
                    updateBreadcrumbs(data.breadcrumbs);
                } else {
                    alert('Error loading folder: ' + data.message);
                }
            },
            error: function () {
                alert('An unexpected error occurred. Please try again.');
            }
        });
    });

    // Function to update the file list dynamically
    function updateFileList(files) {
        var fileListHtml = '';

        files.forEach(function (file) {
            if (file.type === 'folder') {
                fileListHtml += `<li class="list-group-item">
                <i class="file-icon fas fa-folder"></i>
                <button class="btn btn-link folder-btn" data-folder="${file.id}">
                    ${file.name}
                </button>
            </li>`;
            } else {
                fileListHtml += `<li class="list-group-item">
                <i class="file-icon fas fa-file"></i>
                <a href="${file.link}" target="_blank">${file.name}</a>
            </li>`;
            }
        });

        $('#file-list').html(fileListHtml);
    }

    // Function to update breadcrumbs dynamically
    function updateBreadcrumbs(breadcrumbs) {
        let breadcrumbHtml = '<ol class="breadcrumb">';

        breadcrumbs.forEach(function (crumb) {
            breadcrumbHtml += `<li class="breadcrumb-item">
            <button class="btn btn-link breadcrumb-btn" data-folder="${crumb.id}">
                ${crumb.name}
            </button>
        </li>`;
        });

        breadcrumbHtml += '</ol>';

        $('#breadcrumb-list').html(breadcrumbHtml);
    }

</script>
</body>
</html>
