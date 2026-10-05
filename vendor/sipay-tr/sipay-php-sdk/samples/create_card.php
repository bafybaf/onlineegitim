<?php

declare(strict_types=1);

require_once('test_config.php');
/**
 * @var Sipay\Sipay $sipay
 */

$customerNumber = 5070;
$cardNumber = 4508034508034509;
$cardHolderName = "John Doe";
$expiryMonth = 12;
$expiryYear = 2026;

/** @var Sipay\Requests\CreateCardRequest $request */
$request = $sipay->createRequest(Sipay\Requests\CreateCardRequest::class);

$request->setCardHolderName($cardHolderName)
    ->setCardNumber($cardNumber)
    ->setExpiryMonth($expiryMonth)
    ->setExpiryYear($expiryYear)
    ->setCustomerNumber($customerNumber)
    ->setCustomerName("John Doe")
    ->setCustomerEmail("john@doe.com")
    ->setCustomerPhone("901111111111");

$creditCard = $sipay->cardResource()->create($request);

# print result
dump($creditCard);
