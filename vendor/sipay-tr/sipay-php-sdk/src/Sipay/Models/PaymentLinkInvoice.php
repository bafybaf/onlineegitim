<?php

declare(strict_types=1);

namespace Sipay\Models;

use Sipay\JsonBuilder;
use Sipay\Requests\Concerns\InteractsHashKey;
use Sipay\Requests\Contracts\WithHashKey;
use Sipay\RequestStringBuilder;

class PaymentLinkInvoice extends BasePaymentInvoice implements WithHashKey
{
    use InteractsHashKey;

    private $invoiceDescription;

    private $maxInstallment;

    private $currencyCode;

    public function getInvoiceDescription()
    {
        return $this->invoiceDescription;
    }

    public function setInvoiceDescription($invoiceDescription)
    {
        $this->invoiceDescription = $invoiceDescription;

        return $this;
    }

    public function getMaxInstallment()
    {
        return $this->maxInstallment;
    }

    public function setMaxInstallment($maxInstallment)
    {
        $this->maxInstallment = $maxInstallment;

        return $this;
    }

    public function getCurrencyCode()
    {
        return $this->currencyCode;
    }

    public function setCurrencyCode($currencyCode): self
    {
        $this->currencyCode = $currencyCode;

        return $this;
    }

    public function generateHashKeyParts(): array
    {
        return [
            $this->getTotal(),
            $this->getCurrencyCode(),
            $this->getMerchantKey(),
            $this->getInvoiceId(),
        ];
    }

    public function getJsonObject()
    {
        return JsonBuilder::create()
            ->add("invoice_id", $this->getInvoiceId())
            ->add("invoice_description", $this->getInvoiceDescription())
            ->add("total", $this->getTotal())
            ->addArray("items", $this->getItems())
            ->add("max_installment", $this->getMaxInstallment())
            ->add("cancel_url", $this->getCancelUrl())
            ->add("return_url", $this->getReturnUrl())
            ->add("bill_address1", $this->getBillAddress1())
            ->add("bill_address2", $this->getBillAddress2())
            ->add("bill_city", $this->getBillCity())
            ->add("bill_postcode", $this->getBillPostcode())
            ->add("bill_state", $this->getBillState())
            ->add("bill_country", $this->getBillCountry())
            ->add("bill_phone", $this->getBillPhone())
            ->add("bill_email", $this->getBillEmail())
            ->add("discount", $this->getDiscount())
            ->add("coupon", $this->getCoupon())
            ->add("transaction_type", $this->getTransactionType())
            ->add("order_type", $this->getOrderType())
            ->add("recurring_payment_number", $this->getRecurringPaymentNumber())
            ->add("recurring_payment_cycle", $this->getRecurringPaymentCycle())
            ->add("recurring_payment_interval", $this->getRecurringPaymentInterval())
            ->add("recurring_web_hook_key", $this->getRecurringWebHookKey())
            ->add("sale_web_hook_key", $this->getSaleWebHookKey())
            ->add("hash_key", $this->getHashKey())
            ->getObject();
    }

    public function toPKIRequestString()
    {
        return RequestStringBuilder::create()
            ->append("invoice_id", $this->getInvoiceId())
            ->append("invoice_description", $this->getInvoiceDescription())
            ->append("total", $this->getTotal())
            ->appendArray("items", $this->getItems())
            ->append("max_installment", $this->getMaxInstallment())
            ->append("cancel_url", $this->getCancelUrl())
            ->append("return_url", $this->getReturnUrl())
            ->append("bill_address1", $this->getBillAddress1())
            ->append("bill_address2", $this->getBillAddress2())
            ->append("bill_city", $this->getBillCity())
            ->append("bill_postcode", $this->getBillPostcode())
            ->append("bill_state", $this->getBillState())
            ->append("bill_country", $this->getBillCountry())
            ->append("bill_phone", $this->getBillPhone())
            ->append("bill_email", $this->getBillEmail())
            ->append("discount", $this->getDiscount())
            ->append("coupon", $this->getCoupon())
            ->append("transaction_type", $this->getTransactionType())
            ->append("order_type", $this->getOrderType())
            ->append("recurring_payment_number", $this->getRecurringPaymentNumber())
            ->append("recurring_payment_cycle", $this->getRecurringPaymentCycle())
            ->append("recurring_payment_interval", $this->getRecurringPaymentInterval())
            ->append("recurring_web_hook_key", $this->getRecurringWebHookKey())
            ->append("sale_web_hook_key", $this->getSaleWebHookKey())
            ->append("hash_key", $this->getHashKey())
            ->getRequestString();
    }
}
