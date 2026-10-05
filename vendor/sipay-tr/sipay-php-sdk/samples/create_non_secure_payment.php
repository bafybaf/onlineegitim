<?php

declare(strict_types=1);

require_once('test_config.php');
/**
 * @var Sipay\Sipay $sipay
 */

$orderId = random_int(1000000, 9999999);

$items = [
    $sipay->createModel(Sipay\Models\PaymentLinkInvoiceItem::class)
    ->setName('Test Product 1')
    ->setPrice(100)
    ->setQuantity(1)
    ->setDescription('Test Product Description 1'),
    $sipay->createModel(Sipay\Models\PaymentLinkInvoiceItem::class)
        ->setName('Test Product 2')
        ->setPrice(50)
        ->setQuantity(2)
        ->setDescription('Test Product Description 2'),
    $sipay->createModel(Sipay\Models\PaymentLinkInvoiceItem::class)
        ->setName('Test Product 3')
        ->setPrice(50.35)
        ->setQuantity(1)
        ->setDescription('Test Product Description 3'),
];

$total = array_reduce($items, function ($carry, $item) {
    return $carry + ($item->getPrice() * $item->getQuantity());
}, 0);

$installment = '3';
$currencyCode = Sipay\Enums\CurrencyCode::TRY;
$invoiceId = $orderId.'-'.date('dmY').'-'.rand(100, 999);

/** @var Sipay\Models\NonSecurePaymentInvoice $paymentInvoice */
$paymentInvoice = $sipay->createModel(Sipay\Models\NonSecurePaymentInvoice::class);
$paymentInvoice
    ->setCurrencyCode($currencyCode)
    ->setInvoiceId($invoiceId)
    ->setTotal($total)
    ->setItems($items)
    ->setInstallmentsNumber($installment)
    ->setCancelUrl('https://webhook.site/ad72f0fd-7644-4f22-878b-abc2e0d35f1c')
    ->setReturnUrl('https://webhook.site/ad72f0fd-7644-4f22-878b-abc2e0d35f1c')
    ->setName('John')
    ->setSurname('Doe')
    ->setBillAddress1('Test Address 1')
    ->setBillAddress2('Test Address 2')
    ->setBillCity('Istanbul')
    ->setBillPostcode('34528')
    ->setBillState('Beylikdüzü')
    ->setBillCountry('TURKEY')
    ->setBillPhone('905555555555')
    ->setBillEmail('example@gimali.com')
    ->setIp('203.0.113.10')
    ->setTransactionType(Sipay\Enums\TransactionType::PRE_AUTHORIZATION)
    ->setNewCard('John Doe', '4508034508034509', '12', '2026', '000');

/** @var Sipay\Requests\CreateNonSecurePaymentRequest $request */
$request = $sipay->createRequest(Sipay\Requests\CreateNonSecurePaymentRequest::class);
$request->setPaymentInvoice($paymentInvoice);

$nonSecurePayment = $sipay->nonSecurePaymentResource()->create($request);

dump($nonSecurePayment);
