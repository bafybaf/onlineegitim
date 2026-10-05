<?php

declare(strict_types=1);

use Sipay\Requests\ConfirmPaymentRequest;

require_once('test_config.php');
/**
 * @var Sipay\Sipay $sipay
 */

$total = 200;
$invoiceId = '3755404-12022025-669';

$request = $sipay->createRequest(ConfirmPaymentRequest::class);
$request
    ->setInvoiceId($invoiceId)
    ->setTotal($total)
    ->setStatus(Sipay\Enums\ConfirmPaymentStatus::COMPLETE);

$confirmPayment = $sipay->confirmPaymentResource()->create($request);

dump($confirmPayment);
