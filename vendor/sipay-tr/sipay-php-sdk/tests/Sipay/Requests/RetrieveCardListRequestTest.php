<?php

declare(strict_types=1);

namespace Sipay\Requests;

use Sipay\TestCase;

class RetrieveCardListRequestTest extends TestCase
{
    private RetrieveCardListRequest $request;

    protected function setUp(): void
    {
        $options = $this->createTestOptions();
        $this->request = new RetrieveCardListRequest($options);

        // Set default test values
        $this->request->setCustomerNumber(5070);
    }

    public function testGetJsonObject(): void
    {
        $jsonObject = $this->request->getJsonObject();

        $this->assertIsArray($jsonObject);
        $this->assertEquals('test_merchant_key', $jsonObject['merchant_key']);
        $this->assertEquals(5070, $jsonObject['customer_number']);
    }

    public function testToPKIRequestString(): void
    {
        $requestString = $this->request->toPKIRequestString();

        $this->assertIsString($requestString);
        $this->assertStringContainsString('merchant_key=test_merchant_key', $requestString);
        $this->assertStringContainsString('customer_number=5070', $requestString);
    }

    public function testSetAndGetCustomerNumber(): void
    {
        $this->request->setCustomerNumber(1234);
        $this->assertEquals(1234, $this->request->getCustomerNumber());
    }
}
