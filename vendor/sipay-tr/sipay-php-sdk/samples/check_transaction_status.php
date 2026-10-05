<?php


declare(strict_types=1);

use Sipay\Requests\CheckTransactionStatusRequest;

require_once('test_config.php');
/**
 * @var Sipay\Sipay $sipay
 */

$orderId = 'VP17393731163874691';
$invoiceId = '3755404-12022025-669';

$checkRequest = $sipay->createRequest(CheckTransactionStatusRequest::class);
$checkRequest
    ->setInvoiceId($invoiceId)
    ->setIncludePendingStatus(true);

$checkResponse = $sipay->transactionStatusResource()->retrieve($checkRequest);

dump($checkResponse);
dump(PHP_EOL);
