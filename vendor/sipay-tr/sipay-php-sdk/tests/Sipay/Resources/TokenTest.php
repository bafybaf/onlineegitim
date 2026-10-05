<?php

declare(strict_types=1);

namespace Sipay\Resources;

use PHPUnit\Framework\TestCase;
use Sipay\Contracts\HttpClient;
use Sipay\Curl\CurlResponse;
use Sipay\Exceptions\InvalidRequestException;
use Sipay\Requests\RetrieveTokenRequest;
use Sipay\SipayOptions;

class TokenTest extends TestCase
{
    private $mockHttpClient;

    private $tokenResource;

    private $options;

    protected function setUp(): void
    {
        $this->mockHttpClient = $this->createMock(HttpClient::class);
        $this->options = new SipayOptions('test_key', 'test_secret');
        $this->tokenResource = new Token($this->options, $this->mockHttpClient);
    }

    public function testRetrieveWithSuccessfulResponse(): void
    {
        $request = new RetrieveTokenRequest($this->options);
        $request->setAppId('test_key');
        $request->setAppSecret('test_secret');

        $responseData = [
            'status_code' => 100,
            'status_description' => 'Successful',
            'data' => [
                'token' => 'test_token_123',
                'is_3d' => 1,
                'expires_at' => '2025-01-14 22:53:53',
            ],
        ];

        $this->mockHttpClient
            ->expects($this->once())
            ->method('post')
            ->with(
                $this->stringContains('/api/token'),
                $this->anything(),
                $this->callback(function ($params) {
                    return is_array($params)
                        && array_key_exists('app_id', $params)
                        && $params['app_id'] === 'test_key'
                        && array_key_exists('app_secret', $params)
                        && $params['app_secret'] === 'test_secret';
                })
            )
            ->willReturn(new CurlResponse(json_encode($responseData, JSON_THROW_ON_ERROR), 200, []));

        $result = $this->tokenResource->retrieve($request);

        $this->assertEquals('test_token_123', $result->getToken());
        $this->assertEquals(1, $result->getIs3D());
        $this->assertEquals('2025-01-14 22:53:53', $result->getExpiresAt());
    }

    public function testRetrieveWithInvalidCredentials(): void
    {
        $request = new RetrieveTokenRequest($this->options);
        $request->setAppId('invalid_key');
        $request->setAppSecret('invalid_secret');

        $responseData = [
            'status_code' => 30,
            'status_description' => 'Geçersiz kimlik bilgileri',
            'data' => null,
        ];

        $this->mockHttpClient
            ->expects($this->once())
            ->method('post')
            ->with(
                $this->stringContains('/api/token'),
                $this->anything(),
                $this->callback(function ($params) {
                    return is_array($params)
                        && array_key_exists('app_id', $params)
                        && $params['app_id'] === 'invalid_key'
                        && array_key_exists('app_secret', $params)
                        && $params['app_secret'] === 'invalid_secret';
                })
            )
            ->willReturn(new CurlResponse(json_encode($responseData, JSON_THROW_ON_ERROR), 200, []));

        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessage('Geçersiz kimlik bilgileri');

        $this->tokenResource->retrieve($request);
    }

    public function testRetrieveWithMissingAppId(): void
    {
        $request = new RetrieveTokenRequest($this->options);
        $request->setAppSecret('test_secret');

        $responseData = [
            'status_code' => 1,
            'status_description' => 'app id alanı gereklidir.',
            'data' => null,
        ];

        $this->mockHttpClient
            ->expects($this->once())
            ->method('post')
            ->with(
                $this->stringContains('/api/token'),
                $this->anything(),
                $this->callback(function ($params) {
                    return is_array($params)
                        && !array_key_exists('app_id', $params)
                        && array_key_exists('app_secret', $params)
                        && $params['app_secret'] === 'test_secret';
                })
            )
            ->willReturn(new CurlResponse(json_encode($responseData, JSON_THROW_ON_ERROR), 200, []));

        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessage('app id alanı gereklidir.');

        $this->tokenResource->retrieve($request);
    }
}
