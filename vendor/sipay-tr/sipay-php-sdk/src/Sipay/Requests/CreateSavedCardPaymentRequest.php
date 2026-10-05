<?php

declare(strict_types=1);

namespace Sipay\Requests;

use Sipay\Models\SavedCardPaymentInvoice;
use Sipay\RequestPayload;

class CreateSavedCardPaymentRequest extends RequestPayload
{
    private SavedCardPaymentInvoice $paymentInvoice;

    public function getPaymentInvoice(): SavedCardPaymentInvoice
    {
        return $this->paymentInvoice;
    }

    public function setPaymentInvoice(SavedCardPaymentInvoice $paymentInvoice): self
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
