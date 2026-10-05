<?php

declare(strict_types=1);

namespace Sipay\Resources\Mapper;

use Sipay\Resources\CompletePayment;
use Sipay\Resources\Mapper\Contracts\JsonMapper;

/**
 * @property CompletePayment $resource
 */
class CompletePaymentMapper extends SipayResourceMapper implements JsonMapper
{
    public function mapJsonBody(): CompletePayment
    {
        if (property_exists($this->jsonBody, 'data')) {
            $data = $this->jsonBody->data;
            if (property_exists($data, 'sipay_status')) {
                $this->resource->setSipayStatus($data->sipay_status);
            }
            if (property_exists($data, 'order_no')) {
                $this->resource->setOrderNo($data->order_no);
            }
            if (property_exists($data, 'order_id')) {
                $this->resource->setOrderId($data->order_id);
            }
            if (property_exists($data, 'invoice_id')) {
                $this->resource->setInvoiceId($data->invoice_id);
            }
            if (property_exists($data, 'sipay_payment_method')) {
                $this->resource->setSipayPaymentMethod($data->sipay_payment_method);
            }
            if (property_exists($data, 'credit_card_no')) {
                $this->resource->setCreditCardNo($data->credit_card_no);
            }
            if (property_exists($data, 'transaction_type')) {
                $this->resource->setTransactionType($data->transaction_type);
            }
            if (property_exists($data, 'payment_status')) {
                $this->resource->setPaymentStatus($data->payment_status);
            }
            if (property_exists($data, 'payment_method')) {
                $this->resource->setPaymentMethod($data->payment_method);
            }
            if (property_exists($data, 'auth_code')) {
                $this->resource->setAuthCode($data->auth_code);
            }
            if (property_exists($data, 'installment')) {
                $this->resource->setInstallment($data->installment);
            }
            if (property_exists($data, 'amount')) {
                $this->resource->setAmount($data->amount);
            }
            if (property_exists($data, 'status')) {
                $this->resource->setStatus($data->status);
            }
            if (property_exists($data, 'hash_key')) {
                $this->resource->setHashKey($data->hash_key);
            }
        }

        return $this->resource;
    }
}
