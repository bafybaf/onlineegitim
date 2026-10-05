<?php

declare(strict_types=1);

require_once('test_config.php');
/**
 * @var Sipay\Sipay $sipay
 */
$cardToken = 'a4f8173943bfddd48967c18b3b6b850f';
$customerNumber = 5070;
$cardHolderName = "John Not Doe";
$expiryMonth = 12;
$expiryYear = 2026;

/** @var Sipay\Requests\UpdateCardRequest $request */
$request = $sipay->createRequest(Sipay\Requests\UpdateCardRequest::class);

$request->setCardToken($cardToken)
    ->setCustomerNumber($customerNumber)
    ->setExpiryMonth($expiryMonth)
    ->setExpiryYear($expiryYear)
    ->setCardHolderName($cardHolderName)
;

$creditCard = $sipay->cardResource()->update($request);

# print result
dump($creditCard);
