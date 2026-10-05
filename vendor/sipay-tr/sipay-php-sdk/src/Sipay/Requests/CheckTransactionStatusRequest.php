<?php

declare(strict_types=1);

namespace Sipay\Requests;

use Sipay\JsonBuilder;
use Sipay\RequestPayload;
use Sipay\Requests\Concerns\InteractsHashKey;
use Sipay\Requests\Contracts\WithHashKey;
use Sipay\RequestStringBuilder;

class CheckTransactionStatusRequest extends RequestPayload implements WithHashKey
{
    use InteractsHashKey;

    private string $invoiceId;

    private bool $includePendingStatus;

    public function getInvoiceId(): string
    {
        return $this->invoiceId;
    }

    public function setInvoiceId(string $invoiceId): self
    {
        $this->invoiceId = $invoiceId;

        return $this;
    }

    public function getIncludePendingStatus(): bool
    {
        return $this->includePendingStatus;
    }

    public function setIncludePendingStatus(bool $includePendingStatus): self
    {
        $this->includePendingStatus = $includePendingStatus;

        return $this;
    }

    public function generateHashKeyParts(): array
    {
        return [
            $this->getInvoiceId(),
            $this->getMerchantKey(),
        ];
    }

    public function getJsonObject()
    {
        return JsonBuilder::create()
            ->add("merchant_key", $this->getMerchantKey())
            ->add("invoice_id", $this->getInvoiceId())
            ->add("include_pending_status", $this->getIncludePendingStatus())
            ->add("hash_key", $this->getHashKey())
            ->getObject();
    }

    public function toPKIRequestString()
    {
        return RequestStringBuilder::create()
            ->append("merchant_key", $this->getMerchantKey())
            ->append("invoice_id", $this->getInvoiceId())
            ->append("include_pending_status", $this->getIncludePendingStatus())
            ->append("hash_key", $this->getHashKey())
            ->getRequestString();
    }
}
