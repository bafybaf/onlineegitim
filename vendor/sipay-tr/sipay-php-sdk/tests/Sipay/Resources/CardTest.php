<?php

declare(strict_types=1);

namespace Sipay\Resources;

use Sipay\Enums\ResponseStatusCode;
use Sipay\Exceptions\InvalidRequestException;
use Sipay\Requests\CreateCardRequest;
use Sipay\TestCase;

class CardTest extends TestCase
{
    private $mockHttpClient;

    private $cardResource;

    private $options;

    protected function setUp(): void
    {
        $this->mockHttpClient = $this->createMockHttpClient();
        $this->options = $this->createTestOptions();

        $mockToken = $this->createMock(Token::class);
        $mockToken->method('getToken')->willReturn('test_token_123');
        $mockToken->method('getStatusCode')->willReturn(100);

        $this->cardResource = $this->getMockBuilder(Card::class)
            ->setConstructorArgs([$this->options, $this->mockHttpClient])
            ->onlyMethods(['getAuthenticationToken'])
            ->getMock();

        $this->cardResource->method('getAuthenticationToken')->willReturn($mockToken);
    }

    public function testCreateWithSuccessfulResponse(): void
    {
        $request = new CreateCardRequest($this->options);
        $request->setCardHolderName('John Doe')
            ->setCardNumber(4508034508034509)
            ->setExpiryMonth(12)
            ->setExpiryYear(2026)
            ->setCustomerNumber(5070)
            ->setCustomerName('John Doe')
            ->setCustomerEmail('john@doe.com')
            ->setCustomerPhone('901111111111');

        $this->mockHttpClient
            ->expects($this->once())
            ->method('post')
            ->with(
                $this->stringContains('/api/saveCard'),
                $this->anything(),
                $this->anything()
            )
            ->willReturn($this->createMockResponse([
                'status_code' => ResponseStatusCode::SUCCESS,
                'status_description' => 'The card token saved successfully',
                'card_token' => 'f04cc117d3fbfb149c7cff234cdf5fa7',
            ]));

        $result = $this->cardResource->create($request);

        $this->assertInstanceOf(Card::class, $result);
        $this->assertEquals('f04cc117d3fbfb149c7cff234cdf5fa7', $result->getCardToken());
        $this->assertEquals(100, $result->getStatusCode());
        $this->assertEquals('The card token saved successfully', $result->getStatusDescription());
    }

    public function testCreateWithCardSaveError(): void
    {
        $request = new CreateCardRequest($this->options);
        $request->setCardHolderName('John Doe')
            ->setCardNumber(4508034508034509)
            ->setExpiryMonth(12)
            ->setExpiryYear(2026)
            ->setCustomerNumber(5070)
            ->setCustomerName('John Doe')
            ->setCustomerEmail('john@doe.com')
            ->setCustomerPhone('901111111111');

        $this->mockHttpClient
            ->expects($this->once())
            ->method('post')
            ->with(
                $this->stringContains('/api/saveCard'),
                $this->anything(),
                $this->anything()
            )
            ->willReturn($this->createMockResponse([
                'status_code' => ResponseStatusCode::CARD_TOKEN_SAVE_FAILED,
                'status_description' => 'Kart token kaydetme başarısız oldu',
            ]));

        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessage('Kart token kaydetme başarısız oldu');

        $this->cardResource->create($request);
    }
}
