<?php

declare(strict_types=1);

namespace Lubnik2005\SharepointFlysystem\Microsoft\Drive;

use Lubnik2005\SharepointFlysystem\Microsoft\ApiConnector;
use Exception;
use GuzzleHttp\RequestOptions;

class DirectoryService
{
    private ApiConnector $apiConnector;
    private string $driveId;
    private string $prefix;

    public function __construct(
        string $accessToken,
        string $driveId,
        string $prefix,
        int $requestTimeout = 60,
        bool $verify = true
    ) {
        $this->setApiConnector(new ApiConnector($accessToken, $requestTimeout, $verify));
        $this->setDriveId($driveId);
        $this->setPrefix($prefix);
    }

    public function getApiConnector(): ApiConnector
    {
        return $this->apiConnector;
    }

    public function setApiConnector(ApiConnector $apiConnector): DirectoryService
    {
        $this->apiConnector = $apiConnector;
        return $this;
    }

    public function getDriveId(): string
    {
        return $this->driveId;
    }

    public function getPrefix(): string
    {
        return $this->prefix;
    }

    public function setPrefix(string $prefix): DirectoryService
    {
        $this->prefix = $prefix;
        return $this;
    }

    public function setDriveId(string $driveId): DirectoryService
    {
        $this->driveId = $driveId;
        return $this;
    }

    /**
     * List all items in a specific directory
     *
     * @return array<string, mixed>
     * @throws Exception
     */
    public function requestDirectoryItems(?string $directory = '/', ?string $itemId = null): array
    {
        $url = $this->getDirectoryBaseUrl($directory, $itemId, 'children?$top=50000');
        // /sites/{siteId}/drive
        $response = $this->apiConnector->request('GET', $url);

        if (! isset($response['value'])) {
            throw new \Exception(
                'Microsoft SP Drive Request: Cannot parse the body of the sharepoint drive request. ' . __FUNCTION__,
                2321
            );
        }

        return $response['value'];
    }

    /**
     * Read the directory metadata and so check if it exists
     *
     * @return array<string, mixed>
     * @throws Exception
     */
    public function requestDirectoryMetadata(?string $directory = null, ?string $itemId = null): ?array
    {
        $url = $this->getDirectoryBaseUrl($directory, $itemId);

        $response = $this->apiConnector->request('GET', $url);

        if (isset($response['error'], $response['error']['code']) && $response['error']['code'] === 'itemNotFound') {
            return null;
        }

        if (! isset($response['id'], $response['name'], $response['webUrl'])) {
            throw new \Exception(
                'Microsoft SP Drive Request: Cannot parse the body of the sharepoint drive request. ' . __FUNCTION__,
                2331
            );
        }

        return $response;
    }

    /**
     * @throws Exception
     */
    public function checkDirectoryExists(?string $directory = null, ?string $itemId = null): bool
    {
        $directoryMetaData = $this->requestDirectoryMetadata($directory, $itemId);

        if (isset($directoryMetaData['file'])) {
            throw new \Exception('Check for file exists but path is actually a directory', 2231);
        }

        return $directoryMetaData !== null;
    }

    /**
     * @return array<string, mixed>|null
     * @throws Exception
     */
    public function createDirectory(string $directory, ?string $parentDirectoryId = null): ?array
    {
        if ($directory === '/') {
            throw new \Exception('Cannot create the root directory, this already exists', 2351);
        }

        // Explode the path
        $parent = explode('/', $directory);
        $directoryName = array_pop($parent);

        // build url to fetch the parentItemId if not provided
        if ($parentDirectoryId === null) {
            $parentDirectoryMeta = $this->requestDirectoryMetadata(sprintf('/%s', ltrim(implode('/', $parent), '/')));
            if ($parentDirectoryMeta === null) {
                throw new \Exception('Parent directory does not exists', 2352);
            }
            $parentDirectoryId = $parentDirectoryMeta['id'];
        }

        $url = $this->getDirectoryBaseUrl(null, $parentDirectoryId, 'children?$top=50000');

        // Build request
        $body = [
            'name' => $directoryName,
            'folder' => new \stdClass(),
        ];

        try {
            return $this->apiConnector->request('POST', $url, [], [], null, [
                RequestOptions::JSON => $body,
            ]);
        } catch (\Exception $exception) {
            return null;
        }
    }

    /**
     * @return array<string, mixed>|null
     * @throws Exception
     */
    public function createDirectoryRecursive(string $directory): ?array
    {
        $pathParts = explode('/', $directory);
        $parentDirectoryId = null;
        $createDirectoryResponse = null;
        $fullPathArray = [];

        foreach ($pathParts as $path) {
            $fullPathArray[] = $path;
            $fullPath = sprintf('/%s', ltrim(implode('/', $fullPathArray), '/'));
            $directoryMeta = $this->requestDirectoryMetadata($fullPath);

            if ($directoryMeta !== null) {
                $parentDirectoryId = $directoryMeta['id'];
                continue;
            }

            $createDirectoryResponse = $this->createDirectory($path, $parentDirectoryId);
            if ($createDirectoryResponse === null) {
                throw new \Exception(sprintf('Cannot create recursive the directory %s', $path), 2361);
            }
            if (isset($createDirectoryResponse['error'])) {
                throw new \Exception(
                    sprintf(
                        'Cannot create the directory %s (%s)',
                        $path,
                        $createDirectoryResponse['error']['message']
                    ),
                    2361
                );
            }

            $parentDirectoryId = $createDirectoryResponse['id'];
        }

        return $createDirectoryResponse;
    }

    /**
     * @throws Exception
     */
    public function deleteDirectory(string $directory, ?string $itemId = null): bool
    {
        $url = $this->getDirectoryBaseUrl($directory, $itemId);

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
    private function getDirectoryBaseUrl(?string $path = '/', ?string $itemId = null, ?string $suffix = null): string
    {
        if ($path === null && $itemId === null) {
            throw new \Exception(
                'Microsoft SP Drive Request: Not all the parameters are correctly set. ' . __FUNCTION__,
                2311
            );
        }

        // /drives/{drive-id}/items/{item-id}
        // /drives/{drive-id}/root:/{item-path}
        // https://docs.microsoft.com/en-us/graph/api/driveitem-get?view=graph-rest-1.0&tabs=http
        if ($itemId !== null) {
            return sprintf('/v1.0/drives/%s/items/%s%s', $this->getDriveId(), $itemId, ($suffix ?? ''));
        }

        if ($path === '/' || $path === '') {
            return sprintf('/v1.0/drives/%s/items/root:%s%s', $this->getPrefix() ? '/' . $this->getPrefix() . ':' : '',$this->getDriveId(), ($suffix ?? ''));
        }

        $path = ltrim($path, '/');
        return sprintf(
            '/v1.0/drives/%s/items/root:/$s%s%s',
            $this->getPrefix() ? $this->getPrefix() . '/' : '',
            $this->getDriveId(),
            $path,
            ($suffix !== null ? ':' . $suffix : '')
        );
    }
}