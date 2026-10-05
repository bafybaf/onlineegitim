<?php

declare(strict_types=1);

namespace Sipay\Requests;

use Sipay\JsonBuilder;
use Sipay\Models\PaymentLinkInvoice;
use Sipay\RequestPayload;
use Sipay\RequestStringBuilder;

class CreatePaymentLinkRequest extends RequestPayload
{
    private PaymentLinkInvoice $invoice;

    private string $currencyCode;

    private string $name;

    private string $surname;

    public function getInvoice(): PaymentLinkInvoice
    {
        return $this->invoice;
    }

    /**
     * @param mixed $invoice
     */
    public function setInvoice(PaymentLinkInvoice $invoice): self
    {
        $this->invoice = $invoice;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getSurname(): string
    {
        return $this->surname;
    }

    public function setSurname(string $surname): self
    {
        $this->surname = $surname;

        return $this;
    }

    public function getJsonObject()
    {
        return JsonBuilder::create()
            ->add("merchant_key", $this->getMerchantKey())
            ->add("invoice", $this->getInvoice()->toJsonString())
            ->add("currency_code", $this->getInvoice()->getCurrencyCode())
            ->add("name", $this->getName())
            ->add("surname", $this->getSurname())
            ->getObject();
    }

    public function toPKIRequestString()
    {
        return RequestStringBuilder::create()
            ->append("merchant_key", $this->getMerchantKey())
            ->append("invoice", $this->getInvoice()->toJsonString())
            ->append("currency_code", $this->getInvoice()->getCurrencyCode())
            ->append("name", $this->getName())
            ->append("surname", $this->getSurname())
            ->getRequestString();
    }
}
