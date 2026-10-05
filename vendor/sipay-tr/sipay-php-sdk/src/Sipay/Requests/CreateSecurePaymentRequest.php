<?php

declare(strict_types=1);

namespace Sipay\Requests;

use Sipay\Models\SecurePaymentInvoice;
use Sipay\RequestPayload;

class CreateSecurePaymentRequest extends RequestPayload
{
    private SecurePaymentInvoice $paymentInvoice;

    public function getPaymentInvoice(): SecurePaymentInvoice
    {
        return $this->paymentInvoice;
    }

    public function setPaymentInvoice(SecurePaymentInvoice $paymentInvoice): self
    {
        $this->paymentInvoice = $paymentInvoice;

        return $this;
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
