<?php

declare(strict_types=1);

require_once('test_config.php');
/**
 * @var Sipay\Sipay $sipay
 */

$orderId = 332445;

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

/** @var Sipay\Models\SecurePaymentInvoice $paymentInvoice */
$paymentInvoice = $sipay->createModel(Sipay\Models\SecurePaymentInvoice::class);
$paymentInvoice
    ->setCurrencyCode($currencyCode)
    ->setInvoiceId($invoiceId)
    ->setTotal($total)
    ->setItems($items)
    ->setInstallmentsNumber($installment)
    ->setCancelUrl('https://webhook.site/40b30592-6ac2-4eaf-9c13-9f7c6a510b5d')
    ->setReturnUrl('https://webhook.site/40b30592-6ac2-4eaf-9c13-9f7c6a510b5d')
    ->setName('john')
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
    ->setPaymentCompletedBy(Sipay\Enums\PaymentCompletedBy::MERCHANT)
    ->setTransactionType(Sipay\Enums\TransactionType::AUTH)
    ->setNewCard('John Doe', '4508034508034509', '12', '2026', '000');

/** @var Sipay\Requests\CreateSecurePaymentRequest $request */
$request = $sipay->createRequest(Sipay\Requests\CreateSecurePaymentRequest::class);
$request->setPaymentInvoice($paymentInvoice);

$securePayment = $sipay->securePaymentResource()->create($request);

echo $securePayment->getHtmlForm();
