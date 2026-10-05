<?php

declare(strict_types=1);

namespace Sipay\Requests;

use Sipay\JsonBuilder;
use Sipay\RequestPayload;
use Sipay\RequestStringBuilder;

class RetrieveCardListRequest extends RequestPayload
{
    private int $customerNumber;

    public function getCustomerNumber()
    {
        return $this->customerNumber;
    }

    public function setCustomerNumber(int $customerNumber): self
    {
        $this->customerNumber = $customerNumber;

        return $this;

    }

    public function getJsonObject()
    {
        return JsonBuilder::create()
            ->add("merchant_key", $this->getMerchantKey())
            ->add("customer_number", $this->getCustomerNumber())
            ->getObject();
    }

    public function toPKIRequestString()
    {
        return RequestStringBuilder::create()
            ->append("merchant_key", $this->getMerchantKey())
            ->append("customer_number", $this->getCustomerNumber())
            ->getRequestString();
    }
}
