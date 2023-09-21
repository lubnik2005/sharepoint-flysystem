<?php

declare(strict_types=1);

namespace Lubnik2005\SharepointFlysystem\Microsoft\Drive;

use Lubnik2005\SharepointFlysystem\Microsoft\ApiConnector;
use Exception;

class DriveService
{
    private ApiConnector $apiConnector;
    private string $driveId;

    public function __construct(
        string $accessToken,
        int $requestTimeout = 60,
        bool $verify = true
    ) {
        $this->setApiConnector(new ApiConnector($accessToken, $requestTimeout, $verify));
    }

    public function getApiConnector(): ApiConnector
    {
        return $this->apiConnector;
    }

    public function setApiConnector(ApiConnector $apiConnector): DriveService
    {
        $this->apiConnector = $apiConnector;

        return $this;
    }

    public function getDriveId(): string
    {
        return $this->driveId;
    }

    public function setDriveId(string $driveId): DriveService
    {
        $this->driveId = $driveId;

        return $this;
    }

    public function requestDriveIdByName(string $driveName, string $sharepointSiteId): string
    {
        $drives = $this->requestDrives($sharepointSiteId);

        foreach ($drives as $drive) {
            if (!isset($drive['name'])) {
                throw new \Exception(
                    'Microsoft SP Drive Request: Cannot parse the body of the sharepoint drive request. ' .
                    __FUNCTION__,
                    2121
                );
            }
            if ($drive['name'] === $driveName) {
                if (!isset($drive['id'])) {
                    throw new \Exception(
                        'Microsoft SP Drive Request: Cannot parse the body of the sharepoint drive request. ' .
                        __FUNCTION__,
                        2121
                    );
                }

                return $drive['id'];
            }
        }
        throw new \Exception('Microsoft SP Drive Request: Drive "' . $driveName . '" not found. ' . __FUNCTION__, 2121);
    }

    /**
     * @return array<string, mixed>
     * @throws Exception
     */
    public function requestDrives(string $sharepointSiteId): array
    {
        // /sites/{siteId}/drives
        $url = sprintf('/v1.0/sites/%s/drives', $sharepointSiteId);

        $response = $this->apiConnector->request('GET', $url);

        if (
            !isset(
                $response['value']
            )
        ) {
            throw new \Exception(
                'Microsoft SP Drive Request: Cannot parse the body of the sharepoint drive request. ' . __FUNCTION__,
                2111
            );
        }

        return $response['value'];
    }

    /**
     * @return array<string, mixed>
     * @throws Exception
     */
    public function requestDrive(string $sharepointSiteId): array
    {
        // /sites/{siteId}/drive
        $url = sprintf('/v1.0/sites/%s/drive', $sharepointSiteId);

        $response = $this->apiConnector->request('GET', $url);

        if (
            !isset(
                $response['id'],
                $response['description'],
                $response['name'],
                $response['webUrl'],
                $response['owner'],
                $response['quota']
            )
        ) {
            throw new \Exception(
                'Microsoft SP Drive Request: Cannot parse the body of the sharepoint drive request. ' . __FUNCTION__,
                2111
            );
        }

        return $response;
    }

    /**
     * @throws Exception
     */
    public function requestDriveId(string $sharepointSiteId): string
    {
        $drive = $this->requestDrive($sharepointSiteId);

        if (!isset($drive['id'])) {
            throw new \Exception(
                'Microsoft SP Drive Request: Cannot parse the body of the sharepoint drive request. ' . __FUNCTION__,
                2121
            );
        }

        return $drive['id'];
    }

    /**
     * @return array<string, mixed>
     * @throws Exception
     */
    public function requestResourceMetadata(?string $path = null, ?string $itemId = null): ?array
    {
        if ($path === null && $itemId === null) {
            throw new \Exception(
                'Microsoft SP Drive Request: Not all the parameters are correctly set. ' . __FUNCTION__,
                2131
            );
        }

        $url = '';

        // /drives/{drive-id}/items/{item-id}
        // /drives/{drive-id}/root:/{item-path}
        // https://docs.microsoft.com/en-us/graph/api/driveitem-get?view=graph-rest-1.0&tabs=http
        if ($path !== null) {
            $path = ltrim($path, '/');
            $url = sprintf('/v1.0/drives/%s/root:/%s', $this->getDriveId(), $path);
        }

        // Overwrite if itemId is set
        if ($itemId !== null) {
            $url = sprintf('/v1.0/drives/%s/items/%s', $this->getDriveId(), $itemId);
        }

        $response = $this->apiConnector->request('GET', $url);

        if (isset($response['error'], $response['error']['code']) && $response['error']['code'] === 'itemNotFound') {
            return null;
        }

        if (!isset($response['id'], $response['name'], $response['webUrl'])) {
            throw new \Exception(
                'Microsoft SP Drive Request: Cannot parse the body of the sharepoint drive request. ' . __FUNCTION__,
                2132
            );
        }

        return $response;
    }

    /**
     * @throws Exception
     */
    public function checkResourceExists(?string $path = null, ?string $itemId = null): bool
    {
        $fileMetaData = $this->requestResourceMetadata($path, $itemId);

        return $fileMetaData !== null;
    }
}