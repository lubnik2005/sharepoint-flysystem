<?php

declare(strict_types=1);

namespace Lubnik2005\SharepointFlysystem\Microsoft\Drive;

use Lubnik2005\SharepointFlysystem\Microsoft\ApiConnector;
use Exception;
use GuzzleHttp\RequestOptions;

class FileService
{
    private ApiConnector $apiConnector;
    private string $driveId;
    private DirectoryService $directoryService;

    public function __construct(
        string $accessToken,
        string $driveId,
        int $requestTimeout = 60,
        bool $verify = true
    ) {
        $this->setApiConnector(new ApiConnector($accessToken, $requestTimeout, $verify));
        $this->setDriveId($driveId);
        $this->directoryService = new DirectoryService($accessToken, $driveId, $requestTimeout, $verify);
    }

    public function getApiConnector(): ApiConnector
    {
        return $this->apiConnector;
    }

    public function setApiConnector(ApiConnector $apiConnector): FileService
    {
        $this->apiConnector = $apiConnector;
        return $this;
    }

    public function getDriveId(): string
    {
        return $this->driveId;
    }

    public function setDriveId(string $driveId): FileService
    {
        $this->driveId = $driveId;
        return $this;
    }

    /**
     * Read or Download the content of a file by ItemId
     *
     * @throws Exception
     */
    public function readFile(?string $path = null, ?string $itemId = null): string
    {
        $url = $this->getFileBaseUrl($path, $itemId, '/content');

        return $this->apiConnector->request('GET', $url);
    }

    /**
     * @return array<string, mixed>
     * @throws Exception
     */
    public function requestFileMetadata(?string $path = null, ?string $itemId = null): ?array
    {
        $url = $this->getFileBaseUrl($path, $itemId);

        $response = $this->apiConnector->request('GET', $url);

        if (isset($response['error'], $response['error']['code']) && $response['error']['code'] === 'itemNotFound') {
            return null;
        }

        if (! isset($response['id'], $response['name'], $response['webUrl'])) {
            throw new \Exception(
                'Microsoft SP Drive Request: Cannot parse the body of the sharepoint drive request. ' . __FUNCTION__,
                2221
            );
        }

        return $response;
    }

    /**
     * @throws Exception
     */
    public function checkFileExists(?string $path = null, ?string $itemId = null): bool
    {
        $fileMetaData = $this->requestFileMetadata($path, $itemId);

        if (isset($fileMetaData['directory'])) {
            throw new \Exception('Check for file exists but path is actually a directory', 2231);
        }

        return $fileMetaData !== null;
    }

    /**
     * @throws Exception
     */
    public function checkFileLastModified(?string $path = null, ?string $itemId = null): int
    {
        // Will throw exception if file not exists
        $fileMetaData = $this->requestFileMetadata($path, $itemId);

        if ($fileMetaData === null) {
            throw new \Exception('Microsoft SP Drive Request: File not found. ' . __FUNCTION__, 2241);
        }

        if (! isset($fileMetaData['lastModifiedDateTime'])) {
            throw new \Exception(
                'Microsoft SP Drive Request: Cannot parse the body of the sharepoint drive request. ' . __FUNCTION__,
                2242
            );
        }

        return (new \DateTime($fileMetaData['lastModifiedDateTime']))->getTimestamp();
    }

    /**
     * @throws Exception
     */
    public function checkFileMimeType(?string $path = null, ?string $itemId = null): string
    {
        // Will throw exception if file not exists
        $fileMetaData = $this->requestFileMetadata($path, $itemId);

        if ($fileMetaData === null) {
            throw new \Exception('Microsoft SP Drive Request: File not found. ' . __FUNCTION__, 2251);
        }

        if (! isset($fileMetaData['file'], $fileMetaData['file']['mimeType'])) {
            throw new \Exception(
                'Microsoft SP Drive Request: Cannot parse the body of the sharepoint drive request. ' . __FUNCTION__,
                2252
            );
        }

        return $fileMetaData['file']['mimeType'];
    }

    /**
     * @throws Exception
     */
    public function checkFileSize(?string $path = null, ?string $itemId = null): int
    {
        // Will throw exception if file not exists
        $fileMetaData = $this->requestFileMetadata($path, $itemId);

        if ($fileMetaData === null) {
            throw new \Exception('Microsoft SP Drive Request: File not found. ' . __FUNCTION__, 2261);
        }

        if (! isset($fileMetaData['size'])) {
            throw new \Exception(
                'Microsoft SP Drive Request: Cannot parse the body of the sharepoint drive request. ' . __FUNCTION__,
                2263
            );
        }

        return $fileMetaData['size'];
    }

    /**
     * @return array<string,mixed>|null
     * @throws Exception
     */
    public function writeFile(string $path, string $content, string $mimeType = 'text/plain'): ?array
    {
        $parent = explode('/', $path);
        $fileName = array_pop($parent);

        // Create parent directory if not exists
        $parentDirectory = sprintf('/%s', ltrim(implode('/', $parent), '/'));
        if ($parentDirectory !== '/') {
            $this->directoryService->createDirectoryRecursive($parentDirectory);
        }

        $parentDirectoryMeta = $this->directoryService->requestDirectoryMetadata($parentDirectory);
        if ($parentDirectoryMeta === null) {
            throw new \Exception(
                'Microsoft SP Drive Request: No metadata found',
                500
            );
        }
        $parentDirectoryId = $parentDirectoryMeta['id'];

        $url = $this->getFileBaseUrl(null, $parentDirectoryId, sprintf(':/%s:/content', $fileName));

        $response = $this->apiConnector->request('PUT', $url, [], [], $content, [
            RequestOptions::HEADERS => [
                'Content-Type' => $mimeType,
            ],
        ]);

        if ($response) {
            return $response;
        }
        return null;
    }

    /**
     * @return array<string, mixed>
     * @throws Exception
     */
    public function moveFile(string $path, string $targetDirectory, ?string $newName = null): array
    {
        // get current file id,
        $metadata = $this->requestFileMetadata($path);

        if ($metadata === null) {
            throw new \Exception('Microsoft SP Drive Request: File not found. ' . __FUNCTION__, 2271);
        }
        $url = $this->getFileBaseUrl($path, $metadata['id']);

        // get target directory id
        $directoryMeta = $this->directoryService->requestDirectoryMetadata($targetDirectory);

        if ($directoryMeta === null) {
            // create directories recursive
            $this->directoryService->createDirectoryRecursive($targetDirectory);
            $directoryMeta = $this->directoryService->requestDirectoryMetadata($targetDirectory);
            if ($directoryMeta === null) {
                throw new \Exception(
                    'Unable to create the directory ' . __FUNCTION__,
                    500
                );
            }
        }

        // Build request
        $body = [
            'parentReference' => [
                'id' => $directoryMeta['id'],
            ],
        ];

        // add new name to request body when not null
        if ($newName !== null) {
            $body['name'] = $newName;
        }

        return $this->apiConnector->request('PATCH', $url, [], [], null, [
            RequestOptions::JSON => $body,
        ]);
    }

    /**
     * @throws Exception
     */
    public function copyFile(string $path, string $targetDirectory, ?string $newName = null): bool
    {
        // get current file id,
        $metadata = $this->requestFileMetadata($path);

        if ($metadata === null) {
            throw new \Exception('Microsoft SP Drive Request: File not found. ' . __FUNCTION__, 2281);
        }
        $url = $this->getFileBaseUrl(null, $metadata['id'], '/copy');

        // get target directory id
        $directoryMeta = $this->directoryService->requestDirectoryMetadata($targetDirectory);

        if ($directoryMeta === null) {
            // create directories recursive
            $this->directoryService->createDirectoryRecursive($targetDirectory);
            $directoryMeta = $this->directoryService->requestDirectoryMetadata($targetDirectory);
            if ($directoryMeta === null) {
                throw new \Exception(
                    'Unable to create the directory ' . __FUNCTION__,
                    500
                );
            }
        }

        // Build request
        $body = [
            'parentReference' => [
                'driveId' => $this->getDriveId(),
                'id' => $directoryMeta['id'],
            ],
        ];

        // add new name to request body when not null
        if ($newName !== null) {
            $body['name'] = $newName;
        }

        $result = $this->apiConnector->request('POST', $url, [], [], null, [
            RequestOptions::JSON => $body,
        ]);

        if (isset($result['error'], $result['error']['code']) && $result['error']['code'] === 'nameAlreadyExists') {
            throw new Exception('Target file already exists, this is not supported yet.');
        }

        return $result === '';
    }

    /**
     * @throws Exception
     */
    public function deleteFile(?string $path = null, ?string $itemId = null): bool
    {
        $url = $this->getFileBaseUrl($path, $itemId);

        try {
            $this->apiConnector->request('DELETE', $url);
            return true;
        } catch (Exception $exception) {
            return false;
        }
    }

    /**
     * @throws Exception
     */
    private function getFileBaseUrl(?string $path = null, ?string $itemId = null, ?string $suffix = null): string
    {
        if ($path === null && $itemId === null) {
            throw new \Exception(
                'Microsoft SP Drive Request: Not all the parameters are correctly set. ' . __FUNCTION__,
                2211
            );
        }

        // /drives/{drive-id}/items/{item-id}
        // /drives/{drive-id}/root:/{item-path}
        // https://docs.microsoft.com/en-us/graph/api/driveitem-get?view=graph-rest-1.0&tabs=http
        if ($itemId !== null) {
            return sprintf('/v1.0/drives/%s/items/%s%s', $this->getDriveId(), $itemId, ($suffix ?? ''));
        }
        $path = ltrim($path, '/');
        return sprintf(
            '/v1.0/drives/%s/items/root:/%s%s',
            $this->getDriveId(),
            $path,
            ($suffix !== null ? ':' . $suffix : '')
        );
    }
}