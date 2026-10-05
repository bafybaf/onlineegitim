<?php

declare(strict_types=1);

namespace Sipay\Resources\Mapper;

use PHPUnit\Framework\TestCase;
use Sipay\Resources\Token;

class TokenMapperTest extends TestCase
{
    private function createResponse(array $overrides = []): string
    {
        $default = [
            'status_code' => 100,
            'status_description' => 'Successful',
            'data' => [
                'token' => 'test_token_123',
                'is_3d' => 1,
                'expires_at' => '2025-01-14 22:53:53',
            ],
        ];

        return json_encode(array_replace_recursive($default, $overrides));
    }

    public function testMapJsonBodyWithSuccessfulResponse(): void
    {
        $jsonResponse = $this->createResponse();
        $token = new Token();

        $mapper = new TokenMapper($jsonResponse, $token);
        $result = $mapper->mapJsonBody();

        $this->assertEquals('test_token_123', $result->getToken());
        $this->assertEquals(1, $result->getIs3D());
        $this->assertEquals('2025-01-14 22:53:53', $result->getExpiresAt());
    }

    public function testMapJsonBodyWithInvalidCredentials(): void
    {
        $jsonResponse = $this->createResponse([
            'status_code' => 30,
            'status_description' => 'Geçersiz kimlik bilgileri',
            'data' => null,
        ]);
        $token = new Token();

        $mapper = new TokenMapper($jsonResponse, $token);
        $result = $mapper->mapJsonBody();

        $this->assertNull($result->getToken());
        $this->assertNull($result->getIs3D());
        $this->assertNull($result->getExpiresAt());
        $this->assertEquals(30, $result->getStatusCode());
        $this->assertEquals('Geçersiz kimlik bilgileri', $result->getStatusDescription());
    }

    public function testMapJsonBodyWithMissingAppId(): void
    {
        $jsonResponse = $this->createResponse([
            'status_code' => 1,
            'status_description' => 'app id alanı gereklidir.',
            'data' => null,
        ]);
        $token = new Token();

        $mapper = new TokenMapper($jsonResponse, $token);
        $result = $mapper->mapJsonBody();

        $this->assertNull($result->getToken());
        $this->assertNull($result->getIs3D());
        $this->assertNull($result->getExpiresAt());
        $this->assertEquals(1, $result->getStatusCode());
        $this->assertEquals('app id alanı gereklidir.', $result->getStatusDescription());
    }
}
