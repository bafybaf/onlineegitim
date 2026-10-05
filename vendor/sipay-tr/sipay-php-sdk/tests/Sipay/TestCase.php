<?php

declare(strict_types=1);

namespace Sipay;

use PHPUnit\Framework\TestCase as BaseTestCase;
use Sipay\Contracts\HttpClient;
use Sipay\Curl\CurlResponse;

abstract class TestCase extends BaseTestCase
{
    /**
     * Create a mock HTTP client that can be used in tests.
     *
     * @return \PHPUnit\Framework\MockObject\MockObject|HttpClient
     */
    protected function createMockHttpClient()
    {
        return $this->createMock(HttpClient::class);
    }

    /**
     * Create test SipayOptions with default test values.
     */
    protected function createTestOptions(
        ?string $apiKey = 'test_key',
        ?string $apiSecret = 'test_secret',
        ?string $merchantKey = 'test_merchant_key',
        ?string $merchantId = 'test_merchant_id',
        ?string $apiBaseUrl = 'https://provisioning.sipay.com.tr/ccpayment'
    ): SipayOptions {
        return new SipayOptions($apiKey, $apiSecret, $merchantKey, $merchantId, $apiBaseUrl);
    }

    /**
     * Create a mock CurlResponse with the given data.
     *
     * @param array $responseData The data to include in the response body
     * @param int $statusCode HTTP status code
     * @param array $headers Response headers
     */
    protected function createMockResponse(array $responseData, int $statusCode = 200, array $headers = []): CurlResponse
    {
        return new CurlResponse(json_encode($responseData, JSON_THROW_ON_ERROR), $statusCode, $headers);
    }

    /**
     * Create a successful token response.
     *
     * @param string $token The token value
     * @param int $is3D Whether 3D secure is enabled
     * @param string $expiresAt When the token expires
     */
    protected function createSuccessfulTokenResponse(
        string $token = 'test_token_123',
        int $is3D = 1,
        string $expiresAt = '2025-01-14 22:53:53'
    ): CurlResponse {
        $responseData = [
            'status_code' => 100,
            'status_description' => 'Successful',
            'data' => [
                'token' => $token,
                'is_3d' => $is3D,
                'expires_at' => $expiresAt,
            ],
        ];

        return $this->createMockResponse($responseData);
    }

    /**
     * Create an error response.
     *
     * @param int $statusCode The status code
     * @param string $statusDescription The status description
     * @param mixed|null $data Additional data
     */
    protected function createErrorResponse(
        int $statusCode,
        string $statusDescription,
        $data = null
    ): CurlResponse {
        $responseData = [
            'status_code' => $statusCode,
            'status_description' => $statusDescription,
            'data' => $data,
        ];

        return $this->createMockResponse($responseData);
    }
}
