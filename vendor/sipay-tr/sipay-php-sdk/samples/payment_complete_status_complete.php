<?php

declare(strict_types=1);

use Sipay\Requests\CompletePaymentRequest;

require_once('test_config.php');
/**
 * @var Sipay\Sipay $sipay
 */

$orderId = 'VP17393760996160736';
$invoiceId = '332445-12022025-580';

/** @var CompletePaymentRequest $request */
$request = $sipay->createRequest(CompletePaymentRequest::class);
$request
    ->setInvoiceId($invoiceId)
    ->setOrderId($orderId)
    ->setStatus(Sipay\Enums\CompletePaymentStatus::COMPLETE);

$completePayment = $sipay->completePaymentResource()->create($request);

dump($completePayment);
