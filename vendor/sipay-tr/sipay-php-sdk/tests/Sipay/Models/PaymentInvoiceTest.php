<?php

declare(strict_types=1);

namespace Sipay\Models;

use Sipay\Exceptions\InvalidArgumentException;
use Sipay\TestCase;

class PaymentInvoiceTest extends TestCase
{
    /**
     * Each case configures the card the way its endpoint is actually called, so the
     * assertions run against the branch (new card vs. card token) that endpoint uses.
     */
    public function paymentInvoiceProvider(): array
    {
        $newCard = function (PaymentInvoice $invoice): void {
            $invoice->setNewCard('John Doe', '4508034508034509', '12', '2026', '000');
        };

        $savedCard = function (PaymentInvoice $invoice): void {
            $invoice->setSavedCard('card_token_123', 'customer_1', 'john@doe.com', '905555555555', 'John Doe');
        };

        return [
            'non secure payment' => [NonSecurePaymentInvoice::class, $newCard, 'cc_no', 'card_token'],
            'secure payment' => [SecurePaymentInvoice::class, $newCard, 'cc_no', 'card_token'],
            'saved card payment' => [SavedCardPaymentInvoice::class, $savedCard, 'card_token', 'cc_no'],
        ];
    }

    /**
     * @dataProvider paymentInvoiceProvider
     */
    public function testIpIsSentWhenSet(
        string $invoiceClass,
        callable $configureCard,
        string $expectedCardKey,
        string $unexpectedCardKey
    ): void {
        $invoice = $this->createInvoice($invoiceClass, $configureCard)->setIp('192.168.1.10');

        $jsonObject = $invoice->getJsonObject();
        $requestString = $invoice->toPKIRequestString();

        $this->assertEquals('192.168.1.10', $invoice->getIp());

        $this->assertArrayHasKey($expectedCardKey, $jsonObject);
        $this->assertArrayNotHasKey($unexpectedCardKey, $jsonObject);
        $this->assertEquals('192.168.1.10', $jsonObject['ip']);

        $this->assertStringContainsString($expectedCardKey . '=', $requestString);
        $this->assertStringNotContainsString($unexpectedCardKey . '=', $requestString);
        $this->assertStringContainsString('ip=192.168.1.10', $requestString);
    }

    /**
     * @dataProvider paymentInvoiceProvider
     */
    public function testIpIsOmittedWhenNotSet(string $invoiceClass, callable $configureCard): void
    {
        $invoice = $this->createInvoice($invoiceClass, $configureCard);

        $this->assertNull($invoice->getIp());
        $this->assertArrayNotHasKey('ip', $invoice->getJsonObject());
        $this->assertStringNotContainsString('ip=', $invoice->toPKIRequestString());
    }

    public function validIpProvider(): array
    {
        return [
            'IPv4' => ['203.0.113.10', '203.0.113.10'],
            'IPv6' => ['2001:db8::10', '2001:db8::10'],
            'IPv4-mapped IPv6 is unwrapped to IPv4' => ['::ffff:203.0.113.7', '203.0.113.7'],
            'IPv4-mapped IPv6, upper case' => ['::FFFF:203.0.113.7', '203.0.113.7'],
        ];
    }

    /**
     * @dataProvider validIpProvider
     */
    public function testSetIpAcceptsValidAddresses(string $ip, string $expected): void
    {
        $invoice = new NonSecurePaymentInvoice($this->createTestOptions());

        $this->assertEquals($expected, $invoice->setIp($ip)->getIp());
    }

    public function invalidIpProvider(): array
    {
        return [
            'empty string' => [''],
            'X-Forwarded-For list' => ['203.0.113.7, 10.0.0.1'],
            'surrounding whitespace' => [' 203.0.113.7 '],
            'hostname' => ['localhost'],
            'out of range IPv4' => ['256.1.1.1'],
            'array' => [['203.0.113.7']],
            'integer' => [3405803786],
        ];
    }

    /**
     * @dataProvider invalidIpProvider
     */
    public function testSetIpRejectsInvalidValues($ip): void
    {
        $invoice = new NonSecurePaymentInvoice($this->createTestOptions());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Card holder IP address must be a valid IPv4 or IPv6 address.');

        $invoice->setIp($ip);
    }

    public function testSetIpWithNullClearsTheValue(): void
    {
        $invoice = $this->createInvoice(NonSecurePaymentInvoice::class, function (PaymentInvoice $invoice): void {
            $invoice->setNewCard('John Doe', '4508034508034509', '12', '2026', '000');
        });

        $invoice->setIp('203.0.113.10')->setIp(null);

        $this->assertNull($invoice->getIp());
        $this->assertArrayNotHasKey('ip', $invoice->getJsonObject());
    }

    public function testIpDoesNotChangeHashKeyParts(): void
    {
        $invoice = $this->createInvoice(NonSecurePaymentInvoice::class, function (PaymentInvoice $invoice): void {
            $invoice->setNewCard('John Doe', '4508034508034509', '12', '2026', '000');
        });
        $partsWithoutIp = $invoice->generateHashKeyParts();

        $invoice->setIp('192.168.1.10');

        $this->assertEquals($partsWithoutIp, $invoice->generateHashKeyParts());
    }

    private function createInvoice(string $invoiceClass, callable $configureCard): PaymentInvoice
    {
        /** @var PaymentInvoice $invoice */
        $invoice = new $invoiceClass($this->createTestOptions());

        $invoice
            ->setCurrencyCode('TRY')
            ->setInvoiceId('INV-1')
            ->setTotal(100)
            ->setItems([])
            ->setInstallmentsNumber(1);

        $configureCard($invoice);

        return $invoice;
    }
}
