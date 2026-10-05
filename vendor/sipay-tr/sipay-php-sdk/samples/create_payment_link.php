<?php

declare(strict_types=1);

use Sipay\Models\PaymentLinkInvoice;
use Sipay\Models\PaymentLinkInvoiceItem;
use Sipay\Requests\CheckTransactionStatusRequest;
use Sipay\Requests\CreatePaymentLinkRequest;

require_once('test_config.php');
/**
 * @var Sipay\Sipay $sipay
 */

$orderId = 332445;

$items = [
    $sipay->createModel(PaymentLinkInvoiceItem::class)
        ->setName('Test Product 1')
        ->setPrice(100)
        ->setQuantity(1)
        ->setDescription('Test Product Description 1'),
    $sipay->createModel(PaymentLinkInvoiceItem::class)
        ->setName('Test Product 2')
        ->setPrice(50)
        ->setQuantity(2)
        ->setDescription('Test Product Description 2'),
    $sipay->createModel(PaymentLinkInvoiceItem::class)
        ->setName('Test Product 3')
        ->setPrice(50)
        ->setQuantity(1)
        ->setDescription('Test Product Description 3'),
];

$total = array_reduce($items, function ($carry, $item) {
    return $carry + ($item->getPrice() * $item->getQuantity());
}, 0);

$installment = '1';
$currencyCode = Sipay\Enums\CurrencyCode::TRY;
$invoiceId = $orderId.'-'.date('dmY').'-'.rand(100, 999);

$invoice = $sipay->createModel(PaymentLinkInvoice::class);
$invoice->setInvoiceDescription('asd')
    ->setInvoiceId($invoiceId)
    ->setTotal($total)
    ->setItems($items)
    ->setMaxInstallment($installment)
    ->setCancelUrl('https://webhook.site/ad72f0fd-7644-4f22-878b-abc2e0d35f1c')
    ->setReturnUrl('https://webhook.site/ad72f0fd-7644-4f22-878b-abc2e0d35f1c')
    ->setBillAddress1('Test Address 1')
    ->setBillAddress2('Test Address 2')
    ->setBillCity('Istanbul')
    ->setBillPostcode('34528')
    ->setBillState('Beylikdüzü')
    ->setBillCountry('TURKEY')
    ->setBillEmail('example@gimali.com')
    ->setBillPhone('905555555555')
    ->setOrderType(0)
    ->setCurrencyCode($currencyCode)
;

# create request class
/** @var CreatePaymentLinkRequest $request */
$request = $sipay->createRequest(CreatePaymentLinkRequest::class);
$request->setName("John")
    ->setSurname("Doe")
    ->setInvoice($invoice)
;

# make request
$paymentLink = $sipay->paymentLinkResource()->generate($request);

# print result
dump($paymentLink);
dump(PHP_EOL);

$checkRequest = $sipay->createRequest(CheckTransactionStatusRequest::class);
$checkRequest
    ->setInvoiceId($invoice->getInvoiceId())
    ->setIncludePendingStatus(true);

$checkResponse = $sipay->transactionStatusResource()->retrieve($checkRequest);

dump($checkResponse);
dump(PHP_EOL);
