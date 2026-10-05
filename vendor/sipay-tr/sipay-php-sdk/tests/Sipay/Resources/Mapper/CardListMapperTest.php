<?php

declare(strict_types=1);

namespace Sipay\Resources\Mapper;

use Sipay\Resources\Card;
use Sipay\Resources\CardList;
use Sipay\TestCase;

class CardListMapperTest extends TestCase
{
    public function testMapJsonBodyWithValidData(): void
    {
        // Create a sample JSON response
        $jsonResponse = json_encode([
            'status_code' => 100,
            'status_description' => 'Data fetched successfully',
            'data' => [
                [
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
                ],
                [
                    'id' => 1419,
                    'card_token' => '6ba52952307239d649184a744bfc0dba',
                    'card_user_key' => 'cJ>_uWL@_fv<2ad9:tE@_:3GydHE3y5fMzK0Lz9sAjSQPWzqjt6jO36pYplzY2HkhejavM89BRiz2U1BGI1Z9V',
                    'card_number' => '450803****4509',
                    'merchant_id' => 18309,
                    'customer_number' => '5070-18309',
                    'card_issuer_bank' => 'T. IS BANKASI A.S.',
                    'customer_name' => 'John Doe',
                    'customer_email' => 'john@doe.com',
                    'customer_phone' => '901111111111',
                    'bank_id' => 62,
                    'created_at' => '2022-09-10T15:55:29.000000Z',
                    'updated_at' => '2022-09-10T15:55:29.000000Z',
                ],
            ],
        ]);

        // Create a CardList resource
        $cardList = new CardList();

        // Initialize cardDetails to avoid "property must not be accessed before initialization" error
        $cardList->setCardDetails([]);

        // Create the mapper and map the JSON
        $mapper = new CardListMapper($jsonResponse, $cardList);
        $result = $mapper->mapJsonBody();

        // Assertions
        $this->assertInstanceOf(CardList::class, $result);
        $this->assertIsArray($result->getCardDetails());
        $this->assertCount(2, $result->getCardDetails());

        // Check the first card details
        $firstCard = $result->getCardDetails()[0];
        $this->assertInstanceOf(Card::class, $firstCard);
        $this->assertEquals(1418, $firstCard->getId());
        $this->assertEquals('a4f8173943bfddd48967c18b3b6b850f', $firstCard->getCardToken());
        $this->assertEquals('450803****4509', $firstCard->getCardNumber());
        $this->assertEquals('John Doe', $firstCard->getCustomerName());
        $this->assertEquals('john@doe.com', $firstCard->getCustomerEmail());
        $this->assertEquals('901111111111', $firstCard->getCustomerPhone());

        // Check the second card details
        $secondCard = $result->getCardDetails()[1];
        $this->assertInstanceOf(Card::class, $secondCard);
        $this->assertEquals(1419, $secondCard->getId());
        $this->assertEquals('6ba52952307239d649184a744bfc0dba', $secondCard->getCardToken());
    }

    public function testMapJsonBodyWithEmptyData(): void
    {
        // Create a sample JSON response with empty data
        $jsonResponse = json_encode([
            'status_code' => 100,
            'status_description' => 'Data fetched successfully',
            'data' => [],
        ]);

        // Create a CardList resource
        $cardList = new CardList();

        // Initialize cardDetails to avoid "property must not be accessed before initialization" error
        $cardList->setCardDetails([]);

        // Create the mapper and map the JSON
        $mapper = new CardListMapper($jsonResponse, $cardList);
        $result = $mapper->mapJsonBody();

        // Assertions
        $this->assertInstanceOf(CardList::class, $result);
        $this->assertIsArray($result->getCardDetails());
        $this->assertCount(0, $result->getCardDetails());
    }

    public function testMapJsonBodyWithoutData(): void
    {
        // Create a sample JSON response without data field
        $jsonResponse = json_encode([
            'status_code' => 100,
            'status_description' => 'Data fetched successfully',
        ], JSON_THROW_ON_ERROR);

        // Create a CardList resource
        $cardList = new CardList();

        // Initialize cardDetails to avoid "property must not be accessed before initialization" error
        $cardList->setCardDetails([]);

        // Create the mapper and map the JSON
        $mapper = new CardListMapper($jsonResponse, $cardList);
        $result = $mapper->mapJsonBody();

        // Assertions
        $this->assertInstanceOf(CardList::class, $result);
        // CardDetails should not be modified, so it should remain empty
        $this->assertIsArray($result->getCardDetails());
        $this->assertCount(0, $result->getCardDetails());
    }
}
