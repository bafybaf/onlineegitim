<?php

declare(strict_types=1);

namespace Sipay;

use Sipay\Contracts\HttpClient;
use Sipay\Curl\CurlResponse;
use Sipay\Exceptions\AuthenticationException;
use Sipay\Exceptions\BadMethodCallException;
use Sipay\Exceptions\InvalidArgumentException;
use Sipay\Exceptions\InvalidRequestException;
use Sipay\Exceptions\ServerApiException;
use Sipay\Exceptions\UnknownApiException;
use Sipay\Requests\RetrieveTokenRequest;
use Sipay\Resources\Concerns\HasMapper;
use Sipay\Resources\Token;

class SipayResource
{
    use HasMapper;

    protected $options;

    protected $statusCode;

    protected $statusCodeDescription;

    protected $statusDescription;

    protected $httpClient;

    public function __construct(
        ?SipayOptions $options = null,
        ?HttpClient $httpClient = null
    ) {
        $this->setOptions($options);
        $this->httpClient = $httpClient ?? DefaultHttpClient::instance();
    }

    public function getOptions(): ?SipayOptions
    {
        return $this->options;
    }

    public function setOptions(?SipayOptions $options): self
    {
        $this->options = $options;

        return $this;
    }

    protected function getHttpClient(): HttpClient
    {
        return $this->httpClient;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function setStatusCode(int $statusCode): void
    {
        $this->statusCode = $statusCode;
    }

    public function getStatusCodeDescription(): int
    {
        return $this->statusCodeDescription;
    }

    public function setStatusCodeDescription(?string $description): void
    {
        $this->statusCodeDescription = $description;
    }

    public function getStatusDescription()
    {
        return $this->statusDescription;
    }

    public function setStatusDescription($statusDescription): void
    {
        $this->statusDescription = $statusDescription;
    }

    /**
     * @param 'delete'|'get'|'post' $method HTTP method ('get', 'post', etc.)
     * @param string $url URL for the request
     * @param RequestPayload|null $params list of parameters for the request
     */
    protected function request(string $method, string $url, ?RequestPayload $params = null, array $headers = [], bool $withAuth = true)
    {
        $method = strtolower($method);
        $fullUrl = $this->prepareUrl($url);
        $mergedHeaders = $this->mergeHeaders($headers, $withAuth);
        $params = $params->getJsonObject();

        switch ($method) {
            case 'get':
                $curlResponse = $this->getHttpClient()->get($fullUrl, $mergedHeaders, $params);

                break;
            case 'post':
                $curlResponse = $this->getHttpClient()->post($fullUrl, $mergedHeaders, $params);

                break;
            case 'put':
                $curlResponse = $this->getHttpClient()->put($fullUrl, $mergedHeaders, $params);

                break;
            case 'delete':
                $curlResponse = $this->getHttpClient()->delete($fullUrl, $mergedHeaders, $params);

                break;
            default:
                throw new InvalidArgumentException("Invalid method: $method");
        }

        $this->handleErrorResponse($curlResponse);

        return $curlResponse;
    }

    protected function mergeHeaders(array $otherHeaders, bool $withAuth): array
    {
        $headers = $withAuth ? $this->getHttpHeaders() : $this->getHttpHeadersWithoutAuth();

        return array_merge($headers, $otherHeaders);
    }

    protected function getHttpHeadersWithoutAuth(): array
    {
        return [
            "Accept: application/json",
            "Content-type: application/json",
        ];
    }

    protected function getHttpHeaders(): array
    {
        $headers = [
            "Accept: application/json",
            "Content-type: application/json",
        ];

        $token = $this->getAuthenticationToken();

        $headers[] = sprintf("Authorization: %s", $token->getToken());

        return $headers;
    }

    protected function getAuthenticationToken(): Token
    {
        $options = $this->getOptions();

        $request = new RetrieveTokenRequest($options);
        $request->setAppId($options->getApiKey());
        $request->setAppSecret($options->getApiSecret());

        $tokenResource = new Token($options);
        $token = $tokenResource->retrieve($request);

        if ($token->getStatusCode() !== 100 || !$token->getToken()) {
            throw new AuthenticationException("Authorization Token could not be retrieved. Please check your credentials.");
        }

        return $token;
    }

    protected function prepareUrl($path): string
    {
        return rtrim($this->getApiBaseUrl(), '/') . '/' . ltrim($path, '/');
    }

    protected function handleErrorResponse(CurlResponse $response): void
    {
        $httpStatusCode = $response->getStatusCode();
        $responseBody = $response->getBody();

        $jsonResponse = json_decode($responseBody, true);
        $jsonError = \json_last_error();

        $bodyStatusCode = null;
        $message = $responseBody;

        if (null !== $jsonResponse || \JSON_ERROR_NONE === $jsonError) {
            $bodyStatusCode = $jsonResponse['status_code'] ?? null;
            $message = $jsonResponse['status_description'] ?? $jsonResponse['message'] ?? 'Unknown Error';

            if (is_numeric($bodyStatusCode)) {
                $bodyStatusCode = (int) $bodyStatusCode;

                if (100 !== $bodyStatusCode) {
                    throw InvalidRequestException::createException(
                        $message,
                        $bodyStatusCode,
                        null,
                        $jsonResponse,
                        $response->getHeaders(),
                        $bodyStatusCode
                    );
                }
            }
        }

        if ($httpStatusCode >= 200 && $httpStatusCode < 300) {
            return;
        }

        switch ($httpStatusCode) {
            case 400:
                throw InvalidRequestException::createException(
                    $message,
                    $httpStatusCode,
                    $responseBody,
                    $jsonResponse,
                    $response->getHeaders(),
                    $bodyStatusCode
                );
            case 401:
                throw AuthenticationException::createException(
                    $message,
                    $httpStatusCode,
                    $responseBody,
                    $jsonResponse,
                    $response->getHeaders(),
                    $bodyStatusCode
                );
            case 404:
                throw InvalidRequestException::createException(
                    $message,
                    $httpStatusCode,
                    $responseBody,
                    $jsonResponse,
                    $response->getHeaders(),
                    $bodyStatusCode
                );
            case 405:
                throw BadMethodCallException::createException(
                    $message,
                    $httpStatusCode,
                    $responseBody,
                    $jsonResponse,
                    $response->getHeaders(),
                    $bodyStatusCode
                );
            case 500:
                throw ServerApiException::createException(
                    $message,
                    $httpStatusCode,
                    $responseBody,
                    $jsonResponse,
                    $response->getHeaders(),
                    $bodyStatusCode
                );
            default:
                throw UnknownApiException::createException(
                    $message,
                    $httpStatusCode,
                    $responseBody,
                    $jsonResponse,
                    $response->getHeaders(),
                    $bodyStatusCode
                );
        }
    }

    public function getApiBaseUrl(): string
    {
        return $this->getOptions()->getApiBaseUrl();
    }
}
