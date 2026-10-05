<?php

declare(strict_types=1);

use Sipay\Requests\ConfirmPaymentRequest;

require_once('test_config.php');
/**
 * @var Sipay\Sipay $sipay
 */

$total = 200;
$invoiceId = '332445-24012025-952';

$request = $sipay->createRequest(ConfirmPaymentRequest::class);
$request
    ->setInvoiceId($invoiceId)
    ->setTotal($total)
    ->setStatus(Sipay\Enums\ConfirmPaymentStatus::CANCEL);

$confirmPayment = $sipay->confirmPaymentResource()->create($request);

dump($confirmPayment);
