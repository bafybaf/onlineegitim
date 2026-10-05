<?php

declare(strict_types=1);

namespace Sipay\Resources\Mapper;

use Sipay\Resources\Mapper\Contracts\JsonMapper;
use Sipay\Resources\TransactionStatus;

/**
 * @property TransactionStatus $resource
 */
class TransactionStatusMapper extends SipayResourceMapper implements JsonMapper
{
    public function mapJsonBody(): TransactionStatus
    {
        if (isset($this->jsonBody->transaction_status)) {
            $this->resource->setTransactionStatus($this->jsonBody->transaction_status);
        }
        if (isset($this->jsonBody->order_id)) {
            $this->resource->setOrderId($this->jsonBody->order_id);
        }
        if (isset($this->jsonBody->transaction_id)) {
            $this->resource->setTransactionId($this->jsonBody->transaction_id);
        }
        if (isset($this->jsonBody->message)) {
            $this->resource->setMessage($this->jsonBody->message);
        }
        if (isset($this->jsonBody->invoice_id)) {
            $this->resource->setInvoiceId($this->jsonBody->invoice_id);
        }
        if (isset($this->jsonBody->total_refunded_amount)) {
            $this->resource->setTotalRefundedAmount($this->jsonBody->total_refunded_amount);
        }
        if (isset($this->jsonBody->product_price)) {
            $this->resource->setProductPrice($this->jsonBody->product_price);
        }
        if (isset($this->jsonBody->transaction_amount)) {
            $this->resource->setTransactionAmount($this->jsonBody->transaction_amount);
        }
        if (isset($this->jsonBody->ref_number)) {
            $this->resource->setRefNumber($this->jsonBody->ref_number);
        }
        if (isset($this->jsonBody->transaction_type)) {
            $this->resource->setTransactionType($this->jsonBody->transaction_type);
        }
        if (isset($this->jsonBody->cc_no)) {
            $this->resource->setCcNo($this->jsonBody->cc_no);
        }
        if (isset($this->jsonBody->merchant_commission)) {
            $this->resource->setMerchantCommission($this->jsonBody->merchant_commission);
        }
        if (isset($this->jsonBody->user_commission)) {
            $this->resource->setUserCommission($this->jsonBody->user_commission);
        }
        if (isset($this->jsonBody->settlement_date)) {
            $this->resource->setSettlementDate($this->jsonBody->settlement_date);
        }
        if (isset($this->jsonBody->installment)) {
            $this->resource->setInstallment($this->jsonBody->installment);
        }
        if (isset($this->jsonBody->card_type)) {
            $this->resource->setCardType($this->jsonBody->card_type);
        }
        if (isset($this->jsonBody->recurring_id)) {
            $this->resource->setRecurringId($this->jsonBody->recurring_id);
        }
        if (isset($this->jsonBody->recurring_plan_code)) {
            $this->resource->setRecurringPlanCode($this->jsonBody->recurring_plan_code);
        }
        if (isset($this->jsonBody->next_action_date)) {
            $this->resource->setNextActionDate($this->jsonBody->next_action_date);
        }
        if (isset($this->jsonBody->recurring_status)) {
            $this->resource->setRecurringStatus($this->jsonBody->recurring_status);
        }

        return $this->resource;
    }
}
