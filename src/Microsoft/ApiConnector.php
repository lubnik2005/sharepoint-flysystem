<?php

declare(strict_types=1);

namespace Lubnik2005\SharepointFlysystem\Microsoft;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;

class ApiConnector
{
    private string $baseUrl = 'https://graph.microsoft.com';
    private ?string $bearerToken = null;
    /**
     * @var array<string,string>|null
     */
    private ?array $basicAuth = null;
    private int $requestTimeout;
    private bool $verify;
    private Client $client;

    public function __construct(
        ?string $accessToken = null,
        int $requestTimeout = 60,
        bool $verify = true
    ) {
        $this->setRequestTimeout($requestTimeout);
        $this->setVerify($verify);

        if ($accessToken !== null) {
            $this->setBearerToken($accessToken);
        }
        $this->setClient();
    }

    public function getRequestTimeout(): int
    {
        return $this->requestTimeout;
    }

    public function setRequestTimeout(int $requestTimeout): ApiConnector
    {
        $this->requestTimeout = $requestTimeout;
        return $this;
    }

    /**
     * @return array<string, bool>
     */
    public function getVerify(): array
    {
        return [RequestOptions::VERIFY => $this->verify];
    }

    public function setVerify(bool $verify): ApiConnector
    {
        $this->verify = $verify;
        return $this;
    }

    /**
     * @throws Exception
     */
    public function setClient(?Client $client = null): void
    {
        if ($client !== null) {
            $this->client = $client;
            return;
        }

        try {
            $this->client = new Client([
                'base_uri' => $this->getBaseUrl(),
                'allow_redirects' => [
                    'max' => 10,        // Max 10 redirects.
                    'protocols' => ['https'], // only https
                    'referer' => true,      // add a Referer header
                    'strict' => true,      // use "strict" RFC compliant redirects.
                    'track_redirects' => true,
                ],
                'http_errors' => false,
                'timeout' => $this->getRequestTimeout(),
            ]);
        } catch (Exception $exception) {
            throw new Exception(
                'Failed to setup web client, make sure the tenant and site are correct',
                500,
                $exception
            );
        }
    }

    public function getClient(): Client
    {
        return $this->client;
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    public function setBaseUrl(string $baseUrl): ApiConnector
    {
        $this->baseUrl = $baseUrl;
        return $this;
    }

    public function getBearerToken(): ?string
    {
        return $this->bearerToken;
    }

    public function setBearerToken(?string $bearerToken): ApiConnector
    {
        $this->bearerToken = $bearerToken;
        return $this;
    }

    /**
     * @return array<string, string>|null
     */
    public function getBasicAuth(): ?array
    {
        return $this->basicAuth;
    }

    /**
     * @param array<string,string>|null $basicAuth
     */
    public function setBasicAuth(?array $basicAuth): ApiConnector
    {
        $this->basicAuth = $basicAuth;
        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function getAuthenticationHeader(): array
    {
        if ($this->getBasicAuth() !== null) {
            return [RequestOptions::AUTH => $this->getBasicAuth()];
        }

        if ($this->getBearerToken() !== null) {
            return [RequestOptions::HEADERS => ['Authorization' => 'Bearer ' . $this->getBearerToken()]];
        }

        return [];
    }

    /**
     * @return array<array<string>>
     */
    public function getDefaultHeaders(): array
    {
        return [RequestOptions::HEADERS => ['Accept' => 'application/json']];
    }

    /**
     * @param array<string, mixed> $queryParameters
     * @param array<string, mixed> $formData
     * @param array<string, mixed> $options
     * @throws Exception
     */
    public function request(
        string $method,
        string $url,
        array $queryParameters = [],
        array $formData = [],
        ?string $body = null,
        array $options = []
    ): mixed {
        try {
            $method = strtoupper($method);

            $request = [];
            if (count($formData) > 0) {
                $request[RequestOptions::FORM_PARAMS] = $formData;
            }

            if ($body !== null) {
                $request[RequestOptions::BODY] = $body;
            }

            if (count($queryParameters) > 0) {
                $request[RequestOptions::QUERY] = $queryParameters;
            }
            $options = array_merge_recursive(
                $this->getAuthenticationHeader(), // Set auth when available
                $this->getDefaultHeaders(), // Default request for JSON response
                $this->getVerify(), // Able to verify ssl certificate
                $request,
                $options // Additional options (will not be overwritten by the other options)
            );
        } catch (Exception $exception) {
            throw new Exception(
                'Microsoft Graph Request: Cannot prepare the connection call to sharepoint something went wrong',
                500,
                $exception
            );
        }

        try {
            // Actually request
            $response = $this->client->request($method, $url, $options);

            // Get Content
            $rawBody = $response->getBody()->getContents();

            if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 500) {
                // JSON Decode the response
                $responseBody = json_decode($rawBody, true);

                if ($responseBody !== null) {
                    return $responseBody;
                }

                return $rawBody;
            }

            $errorMsg = 'Microsoft Graph Request: Failed request, expected the returnCode 200 but actual %s';
            throw new Exception(sprintf($errorMsg, $response->getStatusCode()), $response->getStatusCode());
        } catch (BadResponseException $exception) {
            $content = $exception->getResponse()->getBody()->getContents();
            $errorMsg = sprintf(
                'Microsoft Graph Request: request to %s KO. The server returns the error: %s',
                $url,
                $content
            );
            throw new Exception($errorMsg, $exception->getResponse()->getStatusCode(), $exception);
        } catch (GuzzleException | Exception $exception) {
            throw new Exception(sprintf('Microsoft Graph Request: request to %s KO', $url), 500, $exception);
        }
    }
}