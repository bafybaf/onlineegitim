<?php

declare(strict_types=1);

namespace Sipay\Requests;

use Sipay\TestCase;

class RetrieveTokenRequestTest extends TestCase
{
    private $request;

    protected function setUp(): void
    {
        $options = $this->createTestOptions();
        $this->request = new RetrieveTokenRequest($options);
    }

    public function testGetJsonObject(): void
    {
        $this->request->setAppId('test_app_id');
        $this->request->setAppSecret('test_app_secret');

        $result = $this->request->getJsonObject();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('app_id', $result);
        $this->assertArrayHasKey('app_secret', $result);
        $this->assertEquals('test_app_id', $result['app_id']);
        $this->assertEquals('test_app_secret', $result['app_secret']);
    }

    public function testToPKIRequestString(): void
    {
        $this->request->setAppId('test_app_id');
        $this->request->setAppSecret('test_app_secret');

        $result = $this->request->toPKIRequestString();

        $this->assertIsString($result);
        $this->assertStringContainsString('app_id=test_app_id', $result);
        $this->assertStringContainsString('app_secret=test_app_secret', $result);
    }
}
