<?php

declare(strict_types=1);

namespace Sipay\Resources\Mapper;

use Sipay\Resources\Card;
use Sipay\Resources\Mapper\Contracts\JsonMapper;

/**
 * @property Card $resource
 */
class CardMapper extends SipayResourceMapper implements JsonMapper
{
    public function mapJsonBody(): Card
    {
        if (isset($this->jsonBody->id)) {
            $this->resource->setId($this->jsonBody->id);
        }
        if (isset($this->jsonBody->card_token)) {
            $this->resource->setCardToken($this->jsonBody->card_token);
        }
        if (isset($this->jsonBody->card_user_key)) {
            $this->resource->setCardUserKey($this->jsonBody->card_user_key);
        }
        if (isset($this->jsonBody->card_number)) {
            $this->resource->setCardNumber($this->jsonBody->card_number);
        }
        if (isset($this->jsonBody->merchant_id)) {
            $this->resource->setMerchantId($this->jsonBody->merchant_id);
        }
        if (isset($this->jsonBody->customer_number)) {
            $this->resource->setCustomerNumber($this->jsonBody->customer_number);
        }
        if (isset($this->jsonBody->card_issuer_bank)) {
            $this->resource->setCardIssuerBank($this->jsonBody->card_issuer_bank);
        }
        if (isset($this->jsonBody->customer_name)) {
            $this->resource->setCustomerName($this->jsonBody->customer_name);
        }
        if (isset($this->jsonBody->customer_email)) {
            $this->resource->setCustomerEmail($this->jsonBody->customer_email);
        }
        if (isset($this->jsonBody->customer_phone)) {
            $this->resource->setCustomerPhone($this->jsonBody->customer_phone);
        }
        if (isset($this->jsonBody->bank_id)) {
            $this->resource->setBankId($this->jsonBody->bank_id);
        }
        if (isset($this->jsonBody->created_at)) {
            $this->resource->setCreatedAt($this->jsonBody->created_at);
        }
        if (isset($this->jsonBody->updated_at)) {
            $this->resource->setUpdatedAt($this->jsonBody->updated_at);
        }

        return $this->resource;
    }
}
