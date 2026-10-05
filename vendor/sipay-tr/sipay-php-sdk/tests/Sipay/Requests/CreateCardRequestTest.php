<?php

declare(strict_types=1);

namespace Sipay\Requests;

use Sipay\TestCase;

class CreateCardRequestTest extends TestCase
{
    private CreateCardRequest $request;

    protected function setUp(): void
    {
        $options = $this->createTestOptions();
        $this->request = new CreateCardRequest($options);

        // Set default test values
        $this->request
            ->setCardHolderName('John Doe')
            ->setCardNumber(4508034508034509)
            ->setExpiryMonth(12)
            ->setExpiryYear(2026)
            ->setCustomerNumber(5070)
            ->setCustomerName('John Doe')
            ->setCustomerEmail('john@doe.com')
            ->setCustomerPhone('901111111111');
    }

    public function testGenerateHashKeyParts(): void
    {
        $expected = [
            'test_merchant_key',
            5070,
            'John Doe',
            4508034508034509,
            12,
            2026,
        ];

        $this->assertEquals($expected, $this->request->generateHashKeyParts());
    }

    public function testGetJsonObject(): void
    {
        $jsonObject = $this->request->getJsonObject();

        $this->assertIsArray($jsonObject);
        $this->assertEquals('test_merchant_key', $jsonObject['merchant_key']);
        $this->assertEquals('John Doe', $jsonObject['card_holder_name']);
        $this->assertEquals(4508034508034509, $jsonObject['card_number']);
        $this->assertEquals(12, $jsonObject['expiry_month']);
        $this->assertEquals(2026, $jsonObject['expiry_year']);
        $this->assertEquals(5070, $jsonObject['customer_number']);
        $this->assertEquals('John Doe', $jsonObject['customer_name']);
        $this->assertEquals('john@doe.com', $jsonObject['customer_email']);
        $this->assertEquals('901111111111', $jsonObject['customer_phone']);
        $this->assertArrayHasKey('hash_key', $jsonObject);
    }

    public function testToPKIRequestString(): void
    {
        $requestString = $this->request->toPKIRequestString();

        $this->assertIsString($requestString);
        $this->assertStringContainsString('merchant_key=test_merchant_key', $requestString);
        $this->assertStringContainsString('card_holder_name=John Doe', $requestString);
        $this->assertStringContainsString('card_number=4508034508034509', $requestString);
        $this->assertStringContainsString('expiry_month=12', $requestString);
        $this->assertStringContainsString('expiry_year=2026', $requestString);
        $this->assertStringContainsString('customer_number=5070', $requestString);
        $this->assertStringContainsString('customer_name=John Doe', $requestString);
        $this->assertStringContainsString('customer_email=john@doe.com', $requestString);
        $this->assertStringContainsString('customer_phone=901111111111', $requestString);
        $this->assertStringContainsString('hash_key=', $requestString);
    }
}
