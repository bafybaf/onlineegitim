<?php

declare(strict_types=1);

use Sipay\Requests\CreateTransactionRefundRequest;

require_once('test_config.php');
/**
 * @var Sipay\Sipay $sipay
 */

$refundAmount = 200;
$invoiceId = '332445123-12022025-309';

$request = $sipay->createRequest(CreateTransactionRefundRequest::class);
$request
    ->setAmount($refundAmount)
    ->setInvoiceId($invoiceId)
    ->setRefundWebHookKey('https://webhook.site/c04e6ec0-a448-4eab-b340-89b3cb104131');

$transactionRefund = $sipay->transactionRefundResource()->create($request);

dump($transactionRefund);
