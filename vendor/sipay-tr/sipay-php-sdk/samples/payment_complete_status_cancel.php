<?php

declare(strict_types=1);

use Sipay\Requests\CompletePaymentRequest;

require_once('test_config.php');
/**
 * @var Sipay\Sipay $sipay
 */

$orderId = 'VP1737724800721724';
$invoiceId = '332445-24012025-376';

/** @var CompletePaymentRequest $request */
$request = $sipay->createRequest(CompletePaymentRequest::class);
$request
    ->setInvoiceId($invoiceId)
    ->setOrderId($orderId)
    ->setStatus(Sipay\Enums\CompletePaymentStatus::CANCEL);

$completePayment = $sipay->completePaymentResource()->create($request);

dump($completePayment);
