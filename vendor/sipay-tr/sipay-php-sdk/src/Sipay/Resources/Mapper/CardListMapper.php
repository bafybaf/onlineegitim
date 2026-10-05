<?php

declare(strict_types=1);

namespace Sipay\Resources\Mapper;

use Sipay\Resources\Card;
use Sipay\Resources\CardList;
use Sipay\Resources\Mapper\Contracts\JsonMapper;

/**
 * @property CardList $resource
 */
class CardListMapper extends SipayResourceMapper implements JsonMapper
{
    public function mapJsonBody(): CardList
    {
        if (isset($this->jsonBody->data)) {
            $this->resource->setCardDetails($this->mapCardDetails($this->jsonBody->data));
        }

        return $this->resource;
    }

    private function mapCardDetails($cardDetails): array
    {
        $cards = [];

        foreach ($cardDetails as $index => $cardDetail) {
            $cards[$index] = CardMapper::create(json_encode($cardDetail), new Card())->mapJsonBody();
        }

        return $cards;
    }
}
