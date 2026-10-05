<?php

declare(strict_types=1);

namespace Sipay\Resources;

use Sipay\Requests\RetrieveCardListRequest;
use Sipay\Resources\Mapper\CardListMapper;
use Sipay\SipayResource;

class CardList extends SipayResource
{
    /**
     * @var array|Card[]
     */
    private array $cardDetails;

    public function retrieve(RetrieveCardListRequest $request): self
    {
        $response = $this->request('get', "/api/getCardTokens", $request);

        return $this->createMapper(CardListMapper::class, $response->getBody(), new self())
            ->mapJsonBody();
    }

    public function setCardDetails(array $cardDetails): self
    {
        $this->cardDetails = $cardDetails;

        return $this;
    }

    public function getCardDetails(): array
    {
        return $this->cardDetails;
    }
}
