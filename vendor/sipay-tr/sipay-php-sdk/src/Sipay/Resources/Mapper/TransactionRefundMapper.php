<?php

declare(strict_types=1);

namespace Sipay\Resources\Mapper;

use Sipay\Resources\Mapper\Contracts\JsonMapper;
use Sipay\Resources\TransactionRefund;

/**
 * @property TransactionRefund $resource
 */
class TransactionRefundMapper extends SipayResourceMapper implements JsonMapper
{
    public function mapJsonBody(): TransactionRefund
    {
        if (isset($this->jsonBody->order_no)) {
            $this->resource->setOrderNo($this->jsonBody->order_no);
        }

        if (isset($this->jsonBody->invoice_id)) {
            $this->resource->setInvoiceId($this->jsonBody->invoice_id);
        }

        if (isset($this->jsonBody->ref_no)) {
            $this->resource->setRefNo($this->jsonBody->ref_no);
        }

        if (isset($this->jsonBody->ref_number)) {
            $this->resource->setRefNumber($this->jsonBody->ref_number);
        }

        return $this->resource;
    }
}
