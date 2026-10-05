<?php

declare(strict_types=1);


use Sipay\Models\PaymentLinkInvoiceItem;
use Sipay\Models\SavedCardPaymentInvoice;
use Sipay\Requests\CreateSavedCardPaymentRequest;

ob_start();
require_once('retrieve_card_list.php');
ob_end_clean();


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
$invoiceId = $orderId . '-' . date('dmY') . '-' . rand(100, 999);

dump(PHP_EOL);
dump(PHP_EOL);

/** @var Sipay\Resources\Card $anyCard */
$anyCard = $cardList->getCardDetails()[0];

/** @var SavedCardPaymentInvoice $paymentInvoice */
$paymentInvoice = $sipay->createModel(SavedCardPaymentInvoice::class);
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
    ->setSavedCard(
        $anyCard->getCardToken(),
        $anyCard->getCustomerNumber(),
        $anyCard->getCustomerEmail(),
        $anyCard->getCustomerPhone(),
        $anyCard->getCustomerName()
    );

$request = $sipay->createRequest(CreateSavedCardPaymentRequest::class);
$request->setPaymentInvoice($paymentInvoice);

$savedCardPayment = $sipay->savedCardPaymentResource()->create($request);

echo $savedCardPayment->getHtmlForm();
