<?php

declare(strict_types=1);

namespace Sipay\Resources\Mapper;

use Sipay\Resources\Mapper\Contracts\JsonMapper;
use Sipay\Resources\NonSecurePayment;

/**
 * @property NonSecurePayment $resource
 */
class NonSecurePaymentMapper extends SipayResourceMapper implements JsonMapper
{
    public function mapJsonBody(): NonSecurePayment
    {
        if (isset($this->jsonBody->data)) {
            if (isset($this->jsonBody->data->sipay_status)) {
                $this->resource->setSipayStatus($this->jsonBody->data->sipay_status);
            }
            if (isset($this->jsonBody->data->order_no)) {
                $this->resource->setOrderNo($this->jsonBody->data->order_no);
            }
            if (isset($this->jsonBody->data->order_id)) {
                $this->resource->setOrderId($this->jsonBody->data->order_id);
            }
            if (isset($this->jsonBody->data->invoice_id)) {
                $this->resource->setInvoiceId($this->jsonBody->data->invoice_id);
            }
            if (isset($this->jsonBody->data->sipay_payment_method)) {
                $this->resource->setSipayPaymentMethod($this->jsonBody->data->sipay_payment_method);
            }
            if (isset($this->jsonBody->data->credit_card_no)) {
                $this->resource->setCreditCardNo($this->jsonBody->data->credit_card_no);
            }
            if (isset($this->jsonBody->data->transaction_type)) {
                $this->resource->setTransactionType($this->jsonBody->data->transaction_type);
            }
            if (isset($this->jsonBody->data->payment_status)) {
                $this->resource->setPaymentStatus($this->jsonBody->data->payment_status);
            }
            if (isset($this->jsonBody->data->payment_method)) {
                $this->resource->setPaymentMethod($this->jsonBody->data->payment_method);
            }
            if (isset($this->jsonBody->data->auth_code)) {
                $this->resource->setAuthCode($this->jsonBody->data->auth_code);
            }
            if (isset($this->jsonBody->data->merchant_commission)) {
                $this->resource->setMerchantCommission($this->jsonBody->data->merchant_commission);
            }
            if (isset($this->jsonBody->data->user_commission)) {
                $this->resource->setUserCommission($this->jsonBody->data->user_commission);
            }
            if (isset($this->jsonBody->data->merchant_commission_percentage)) {
                $this->resource->setMerchantCommissionPercentage($this->jsonBody->data->merchant_commission_percentage);
            }
            if (isset($this->jsonBody->data->merchant_commission_fixed)) {
                $this->resource->setMerchantCommissionFixed($this->jsonBody->data->merchant_commission_fixed);
            }
            if (isset($this->jsonBody->data->installment)) {
                $this->resource->setInstallment($this->jsonBody->data->installment);
            }
            if (isset($this->jsonBody->data->amount)) {
                $this->resource->setAmount($this->jsonBody->data->amount);
            }
            if (isset($this->jsonBody->data->hash_key)) {
                $this->resource->setHashKey($this->jsonBody->data->hash_key);
            }
        }

        return $this->resource;
    }
}
