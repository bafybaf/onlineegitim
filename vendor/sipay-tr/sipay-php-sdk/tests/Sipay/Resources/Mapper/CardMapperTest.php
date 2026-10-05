<?php

declare(strict_types=1);

namespace Sipay\Resources\Mapper;

use Sipay\Resources\Card;
use Sipay\TestCase;

class CardMapperTest extends TestCase
{
    public function testMapJsonBodyWithValidData(): void
    {
        // Create a sample JSON response for a card
        $jsonResponse = json_encode([
            'id' => 1418,
            'card_token' => 'a4f8173943bfddd48967c18b3b6b850f',
            'card_user_key' => 'cJ>_uWL@_fv<2ad9:tE@_:0j/3Ek5hrnD7yzJATjJcyqe5tlrKtBXNFofVeNsvwqGds4D5nHrKXCBrLMzmg9S0',
            'card_number' => '450803****4509',
            'merchant_id' => 18309,
            'customer_number' => '5070-18309',
            'card_issuer_bank' => 'T. IS BANKASI A.S.',
            'customer_name' => 'John Doe',
            'customer_email' => 'john@doe.com',
            'customer_phone' => '901111111111',
            'bank_id' => 62,
            'created_at' => '2022-09-10T15:44:02.000000Z',
            'updated_at' => '2022-09-10T15:44:02.000000Z',
        ]);

        // Create a Card resource
        $card = new Card();

        // Create the mapper and map the JSON
        $mapper = new CardMapper($jsonResponse, $card);
        $result = $mapper->mapJsonBody();

        // Assertions
        $this->assertInstanceOf(Card::class, $result);
        $this->assertEquals(1418, $result->getId());
        $this->assertEquals('a4f8173943bfddd48967c18b3b6b850f', $result->getCardToken());
        $this->assertEquals('cJ>_uWL@_fv<2ad9:tE@_:0j/3Ek5hrnD7yzJATjJcyqe5tlrKtBXNFofVeNsvwqGds4D5nHrKXCBrLMzmg9S0', $result->getCardUserKey());
        $this->assertEquals('450803****4509', $result->getCardNumber());
        $this->assertEquals(18309, $result->getMerchantId());
        $this->assertEquals('5070-18309', $result->getCustomerNumber());
        $this->assertEquals('T. IS BANKASI A.S.', $result->getCardIssuerBank());
        $this->assertEquals('John Doe', $result->getCustomerName());
        $this->assertEquals('john@doe.com', $result->getCustomerEmail());
        $this->assertEquals('901111111111', $result->getCustomerPhone());
        $this->assertEquals(62, $result->getBankId());
        $this->assertEquals('2022-09-10T15:44:02.000000Z', $result->getCreatedAt());
        $this->assertEquals('2022-09-10T15:44:02.000000Z', $result->getUpdatedAt());
    }

    public function testMapJsonBodyWithPartialData(): void
    {
        // Create a sample JSON response with only some fields
        $jsonResponse = json_encode([
            'id' => 1418,
            'card_token' => 'a4f8173943bfddd48967c18b3b6b850f',
            'card_number' => '450803****4509',
            'customer_name' => 'John Doe',
        ]);

        // Create a Card resource
        $card = new Card();

        // Create the mapper and map the JSON
        $mapper = new CardMapper($jsonResponse, $card);
        $result = $mapper->mapJsonBody();

        // Assertions
        $this->assertInstanceOf(Card::class, $result);
        $this->assertEquals(1418, $result->getId());
        $this->assertEquals('a4f8173943bfddd48967c18b3b6b850f', $result->getCardToken());
        $this->assertEquals('450803****4509', $result->getCardNumber());
        $this->assertEquals('John Doe', $result->getCustomerName());

        // Other fields should be null
        $this->assertNull($result->getCardUserKey());
        $this->assertNull($result->getMerchantId());
        $this->assertNull($result->getCustomerNumber());
        $this->assertNull($result->getCardIssuerBank());
        $this->assertNull($result->getCustomerEmail());
        $this->assertNull($result->getCustomerPhone());
        $this->assertNull($result->getBankId());
        $this->assertNull($result->getCreatedAt());
        $this->assertNull($result->getUpdatedAt());
    }

    public function testMapJsonBodyWithCardTokenResponse(): void
    {
        // Create a sample JSON response for a card token save response
        $jsonResponse = json_encode([
            'status_code' => 100,
            'status_description' => 'The card token saved successfully',
            'card_token' => 'f04cc117d3fbfb149c7cff234cdf5fa7',
        ]);

        // Create a Card resource
        $card = new Card();

        // Create the mapper and map the JSON
        $mapper = new CardMapper($jsonResponse, $card);
        $result = $mapper->mapJsonBody();

        // Assertions
        $this->assertInstanceOf(Card::class, $result);
        $this->assertEquals('f04cc117d3fbfb149c7cff234cdf5fa7', $result->getCardToken());
        $this->assertEquals(100, $result->getStatusCode());
        $this->assertEquals('The card token saved successfully', $result->getStatusDescription());
    }
}
