<?php

declare(strict_types=1);

namespace Sipay\Requests;

use Sipay\JsonBuilder;
use Sipay\RequestPayload;
use Sipay\Requests\Concerns\InteractsHashKey;
use Sipay\Requests\Contracts\WithHashKey;
use Sipay\RequestStringBuilder;

class DeleteCardRequest extends RequestPayload implements WithHashKey
{
    use InteractsHashKey;

    private $cardToken;

    private $customerNumber;

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
            ->add('hash_key', $this->getHashKey())
            ->add('merchant_key', $this->getMerchantKey())
            ->getObject();
    }

    public function toPKIRequestString()
    {
        return RequestStringBuilder::create()
            ->append("card_token", $this->getCardToken())
            ->append("customer_number", $this->getCustomerNumber())
            ->append('hash_key', $this->getHashKey())
            ->append('merchant_key', $this->getMerchantKey())
            ->getRequestString();
    }
}
