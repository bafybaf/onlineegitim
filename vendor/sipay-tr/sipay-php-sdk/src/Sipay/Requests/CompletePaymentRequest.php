<?php

declare(strict_types=1);

namespace Sipay\Requests;

use Sipay\JsonBuilder;
use Sipay\RequestPayload;
use Sipay\Requests\Concerns\InteractsHashKey;
use Sipay\Requests\Contracts\WithHashKey;
use Sipay\RequestStringBuilder;

class CompletePaymentRequest extends RequestPayload implements WithHashKey
{
    use InteractsHashKey;

    private $invoiceId;

    private $orderId;

    private $status;

    public function getInvoiceId()
    {
        return $this->invoiceId;
    }

    public function setInvoiceId($invoiceId)
    {
        $this->invoiceId = $invoiceId;

        return $this;
    }

    public function getOrderId()
    {
        return $this->orderId;
    }

    public function setOrderId($orderId)
    {
        $this->orderId = $orderId;

        return $this;
    }

    public function getStatus()
    {
        return $this->status;
    }

    public function setStatus($status)
    {
        $this->status = $status;

        return $this;
    }

    public function getJsonObject()
    {
        return JsonBuilder::create()
            ->add("merchant_key", $this->getMerchantKey())
            ->add("invoice_id", $this->getInvoiceId())
            ->add("order_id", $this->getOrderId())
            ->add("status", $this->getStatus())
            ->add("hash_key", $this->getHashKey())
            ->getObject();
    }

    public function toPKIRequestString()
    {
        return RequestStringBuilder::create()
            ->append("merchant_key", $this->getMerchantKey())
            ->append("invoice_id", $this->getInvoiceId())
            ->append("order_id", $this->getOrderId())
            ->append("status", $this->getStatus())
            ->append("hash_key", $this->getHashKey())
            ->getRequestString();
    }

    public function generateHashKeyParts(): array
    {
        return [
            $this->getMerchantKey(),
            $this->getInvoiceId(),
            $this->getOrderId(),
            $this->getStatus(),
        ];
    }
}
