<?php

declare(strict_types=1);

namespace Sipay\Resources;

use Sipay\Enums\ResponseStatusCode;
use Sipay\Requests\RetrieveCardListRequest;
use Sipay\TestCase;

class CardListTest extends TestCase
{
    private $mockHttpClient;

    private $cardListResource;

    private $options;

    protected function setUp(): void
    {
        $this->mockHttpClient = $this->createMockHttpClient();
        $this->options = $this->createTestOptions();

        $mockToken = $this->createMock(Token::class);
        $mockToken->method('getToken')->willReturn('test_token_123');
        $mockToken->method('getStatusCode')->willReturn(100);

        $this->cardListResource = $this->getMockBuilder(CardList::class)
            ->setConstructorArgs([$this->options, $this->mockHttpClient])
            ->onlyMethods(['getAuthenticationToken'])
            ->getMock();

        $this->cardListResource->method('getAuthenticationToken')->willReturn($mockToken);

        // Initialize cardDetails to avoid "property must not be accessed before initialization" error
        $this->cardListResource->setCardDetails([]);
    }

    public function testRetrieveWithSuccessfulResponse(): void
    {
        // Create a request
        $request = new RetrieveCardListRequest($this->options);
        $request->setCustomerNumber(5070);

        // Mock the HTTP client response
        $this->mockHttpClient
            ->expects($this->once())
            ->method('get')
            ->with(
                $this->stringContains('/api/getCardTokens'),
                $this->anything()
            )
            ->willReturn($this->createMockResponse([
                'status_code' => ResponseStatusCode::SUCCESS,
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
            ]));

        // Call the retrieve method
        $result = $this->cardListResource->retrieve($request);

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

    public function testRetrieveWithEmptyResponse(): void
    {
        // Create a request
        $request = new RetrieveCardListRequest($this->options);
        $request->setCustomerNumber(5070);

        // Mock the HTTP client response with empty data
        $this->mockHttpClient
            ->expects($this->once())
            ->method('get')
            ->with(
                $this->stringContains('/api/getCardTokens'),
                $this->anything()
            )
            ->willReturn($this->createMockResponse([
                'status_code' => ResponseStatusCode::SUCCESS,
                'status_description' => 'Data fetched successfully',
                'data' => [],
            ]));

        // Call the retrieve method
        $result = $this->cardListResource->retrieve($request);

        // Assertions
        $this->assertInstanceOf(CardList::class, $result);
        $this->assertIsArray($result->getCardDetails());
        $this->assertCount(0, $result->getCardDetails());
    }
}
