<?php

namespace App;

require_once __DIR__ . '/../vendor/autoload.php'; //ensure this is still included, as it will not be included by index.php when using namespaces.


use Dotenv\Dotenv;
use Exception;
use Google_Client;
use Google_Service_Drive;

class GoogleDriveService
{
    private Google_Client $client;
    private Google_Service_Drive $service;

    private CacheService $cacheService;
    private string $currentFolderId;

    /**
     * @throws \Google\Exception
     */
    public function __construct(string $folderId = null)
    {
        $dotenv = Dotenv::createImmutable(dirname(__DIR__));

        $dotenv->load();

        $dotenv->required([
            'GOOGLE_CREDENTIALS_PATH',
            'GOOGLE_SERVICE_ACCOUNT',
            'GOOGLE_DEFAULT_FOLDER_ID',
            'GOOGLE_APPLICATION_NAME'
        ])->notEmpty();

        $credentialsPath = $_ENV['GOOGLE_CREDENTIALS_PATH'];
        $appName = $_ENV['GOOGLE_APPLICATION_NAME'];
        $serviceAccount = $_ENV['GOOGLE_SERVICE_ACCOUNT'];
        if ($folderId === null) {
            $defaultFolderId = $_ENV['GOOGLE_DEFAULT_FOLDER_ID'];
        } else {
            $defaultFolderId = $folderId;
        }


        $this->client = new Google_Client();
        $this->client->setApplicationName($appName);
        $this->client->setScopes(Google_Service_Drive::DRIVE_READONLY);
        $this->client->setAuthConfig($credentialsPath);

        if ($serviceAccount) {
            $this->client->setSubject($serviceAccount);
        }

        $this->service = new Google_Service_Drive($this->client);
        $this->currentFolderId = $defaultFolderId;
        $this->cacheService = new CacheService();
    }

    /**
     * @param int $pageSize
     * @return array
     * @throws \Google\Service\Exception|\Psr\Cache\InvalidArgumentException
     */
    public function getFolderContents(int $pageSize = 50): array
    {

        $cacheKey = md5($this->currentFolderId);


        $files = $this->cacheService->get($cacheKey);

        if ($files === null) {
            $results = $this->service->files->listFiles([
                'q' => "'$this->currentFolderId' in parents and trashed = false",
                'fields' => 'nextPageToken, files(id, name, mimeType, webViewLink)',
                'pageSize' => $pageSize
            ]);
            $files = $results->getFiles();
            $this->cacheService->store($cacheKey, $files);
        }
        return $files;
    }

    /**
     * @return array
     * @throws \Google\Service\Exception
     * @throws Exception|\Psr\Cache\InvalidArgumentException
     */
    public function getBreadcrumbs(): array
    {
        $cacheKey = md5($this->currentFolderId . '-crumbs');
        $currentFolderId = $this->currentFolderId;
        $breadcrumbs = $this->cacheService->get($cacheKey);
        if ($breadcrumbs === null) {
            $breadcrumbs = [];
            while ($currentFolderId && $currentFolderId !== 'root') {
                $folder = $this->service->files->get($currentFolderId, ['fields' => 'id, name, parents']);
                array_unshift($breadcrumbs, [
                    'id' => $folder->getId(),
                    'name' => $folder->getName()
                ]);
                $currentFolderId = $folder->getParents()[0] ?? null;
            }
            $this->cacheService->store($cacheKey, $breadcrumbs);
        }

        return $breadcrumbs;
    }


    public function changeFolder(string $folderId): void
    {
        $this->currentFolderId = $folderId;
    }
}