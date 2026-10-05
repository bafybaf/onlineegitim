<?php

declare(strict_types=1);

namespace Sipay\Requests;

use Sipay\Models\NonSecurePaymentInvoice;
use Sipay\RequestPayload;
use Sipay\Requests\Concerns\InteractsHashKey;
use Sipay\Requests\Contracts\WithHashKey;

class CreateNonSecurePaymentRequest extends RequestPayload implements WithHashKey
{
    use InteractsHashKey;

    private NonSecurePaymentInvoice $paymentInvoice;

    public function getPaymentInvoice(): NonSecurePaymentInvoice
    {
        return $this->paymentInvoice;
    }

    public function setPaymentInvoice(NonSecurePaymentInvoice $paymentInvoice): self
    {
        $this->paymentInvoice = $paymentInvoice;

        return $this;
    }

    public function generateHashKeyParts(): array
    {
        return [
            $this->paymentInvoice->getTotal(),
            $this->paymentInvoice->getInstallmentsNumber(),
            $this->paymentInvoice->getCurrencyCode(),
            $this->getMerchantKey(),
            $this->paymentInvoice->getInvoiceId(),
        ];
    }

    public function getJsonObject()
    {
        return $this->paymentInvoice->getJsonObject();
    }

    public function toPKIRequestString()
    {
        return $this->paymentInvoice->toPKIRequestString();
    }
}
