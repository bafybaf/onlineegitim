<?php

declare(strict_types=1);

namespace Sipay\Requests;

use Sipay\JsonBuilder;
use Sipay\RequestPayload;
use Sipay\Requests\Concerns\InteractsHashKey;
use Sipay\Requests\Contracts\WithHashKey;
use Sipay\RequestStringBuilder;

class UpdateCardRequest extends RequestPayload implements WithHashKey
{
    use InteractsHashKey;

    private $cardToken;

    private $customerNumber;

    private $expiryMonth;

    private $expiryYear;

    private $cardHolderName;

    public function getCardToken()
    {
        return $this->cardToken;
    }

    public function setCardToken($cardToken): self
    {
        $this->cardToken = $cardToken;

        return $this;
    }

    public function getCustomerNumber()
    {
        return $this->customerNumber;
    }

    public function setCustomerNumber($customerNumber): self
    {
        $this->customerNumber = $customerNumber;

        return $this;
    }

    public function getExpiryMonth()
    {
        return $this->expiryMonth;
    }

    public function setExpiryMonth($expiryMonth): self
    {
        $this->expiryMonth = $expiryMonth;

        return $this;
    }

    public function getExpiryYear()
    {
        return $this->expiryYear;
    }

    public function setExpiryYear($expiryYear): self
    {
        $this->expiryYear = $expiryYear;

        return $this;
    }

    public function getCardHolderName()
    {
        return $this->cardHolderName;
    }

    public function setCardHolderName($cardHolderName): self
    {
        $this->cardHolderName = $cardHolderName;

        return $this;
    }

    public function generateHashKeyParts(): array
    {
        return [
            $this->getMerchantKey(),
            $this->getCustomerNumber(),
            $this->getCardToken(),
        ];
    }

    public function getJsonObject()
    {
        return JsonBuilder::create()
            ->add("card_token", $this->getCardToken())
            ->add("customer_number", $this->getCustomerNumber())
            ->add("expiry_month", $this->getExpiryMonth())
            ->add("expiry_year", $this->getExpiryYear())
            ->add("card_holder_name", $this->getCardHolderName())
            ->add('hash_key', $this->getHashKey())
            ->add('merchant_key', $this->getMerchantKey())
            ->getObject();
    }

    public function toPKIRequestString()
    {
        return RequestStringBuilder::create()
            ->append("card_token", $this->getCardToken())
            ->append("customer_number", $this->getCustomerNumber())
            ->append("expiry_month", $this->getExpiryMonth())
            ->append("expiry_year", $this->getExpiryYear())
            ->append("card_holder_name", $this->getCardHolderName())
            ->append('hash_key', $this->getHashKey())
            ->append('merchant_key', $this->getMerchantKey())
            ->getRequestString();
    }
}
