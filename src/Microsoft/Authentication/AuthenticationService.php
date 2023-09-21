<?php

declare(strict_types=1);

namespace Lubnik2005\SharepointFlysystem\Microsoft\Authentication;

use Lubnik2005\SharepointFlysystem\Microsoft\ApiConnector;
use Exception;

class AuthenticationService
{
    private ApiConnector $apiConnector;

    public function __construct(
        int $requestTimeout = 60,
        bool $verify = true
    ) {
        $apiConnector = new ApiConnector(null, $requestTimeout, $verify);
        $this->apiConnector = $apiConnector;
    }

    /**
     * tenant could be one of the following values:
     * 'common' => Allows users with both Microsoft/AD accounts (personal or work/school) to sign into the application.
     * 'organizations' => Allows only users with work/school accounts from Azure AD to sign into the application.
     * 'consumers' => Allows only users with personal Microsoft accounts (MSA) to sign into the application.
     * 'tenantId' => tenant's GUID identifier, example: 19db5b3a-10a7-4039-bc37-a57cc3318668
     * 'msdomain' => Either the friendly domain name of the Azure AD tenant
     *
     * @link https://docs.microsoft.com/en-us7/azure/active-directory/develop/active-directory-v2-protocols#endpoints
     * @return array<string, mixed>
     * @throws Exception
     */
    public function requestToken(string $tenantId, string $clientId, string $clientSecret): array
    {
        $url = sprintf('/%s/oauth2/v2.0/token', $tenantId);

        $this->apiConnector->setBaseUrl('https://login.microsoftonline.com');
        $this->apiConnector->setClient();

        $response = $this->apiConnector->request('POST', $url, [], [
            'grant_type' => 'client_credentials',
            'client_id' => $clientId,
            'scope' => 'https://graph.microsoft.com/.default',
            'client_secret' => $clientSecret,
        ]);

        if (
            !isset(
                $response['token_type'],
                $response['expires_in'],
                $response['ext_expires_in'],
                $response['access_token']
            )
        ) {
            throw new \Exception(
                'Microsoft Authenticate Request: Cannot parse the body of the authentication request',
                500
            );
        }

        $this->apiConnector->setBearerToken($response['access_token']);
        return $response;
    }

    /**
     * @link https://docs.microsoft.com/en-us7/azure/active-directory/develop/active-directory-v2-protocols#endpoints
     *
     * @throws Exception
     */
    public function getAccessToken(string $tenantId, string $clientId, string $clientSecret): string
    {
        $token = $this->requestToken($tenantId, $clientId, $clientSecret);

        if (!isset($token['access_token'])) {
            throw new \Exception('Microsoft Authenticate Request: Cannot parse the body of the token request', 500);
        }

        return $token['access_token'];
    }
}