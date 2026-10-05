<?php

declare(strict_types=1);

namespace Sipay\Resources;

use Sipay\Requests\CreateCardRequest;
use Sipay\Requests\DeleteCardRequest;
use Sipay\Requests\UpdateCardRequest;
use Sipay\Resources\Mapper\CardMapper;
use Sipay\SipayResource;

class Card extends SipayResource
{
    private ?int $id = null;

    private ?string $cardToken = null;

    private ?string $cardUserKey = null;

    private ?string $cardNumber = null;

    private ?int $merchantId = null;

    private ?string $customerNumber = null;

    private ?string $cardIssuerBank = null;

    private ?string $customerName = null;

    private ?string $customerEmail = null;

    private ?string $customerPhone = null;

    private ?int $bankId = null;

    private ?string $createdAt = null;

    private ?string $updatedAt = null;

    public function create(CreateCardRequest $request): self
    {
        $response = $this->request('post', "/api/saveCard", $request);

        return $this->createMapper(CardMapper::class, $response->getBody(), new self())
            ->mapJsonBody();
    }

    public function update(UpdateCardRequest $request): self
    {
        $response = $this->request('post', "/api/editCard", $request);

        return $this->createMapper(CardMapper::class, $response->getBody(), new self())
            ->mapJsonBody();
    }

    public function delete(DeleteCardRequest $request): self
    {
        $response = $this->request('post', "/api/deleteCard", $request);

        return $this->createMapper(CardMapper::class, $response->getBody(), new self())
            ->mapJsonBody();
    }

    public function setId(?int $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function setCardToken(?string $cardToken): self
    {
        $this->cardToken = $cardToken;

        return $this;
    }

    public function setCardUserKey(?string $cardUserKey): self
    {
        $this->cardUserKey = $cardUserKey;

        return $this;
    }

    public function setCardNumber(?string $cardNumber): self
    {
        $this->cardNumber = $cardNumber;

        return $this;
    }

    public function setMerchantId(?int $merchantId): self
    {
        $this->merchantId = $merchantId;

        return $this;
    }

    public function setCustomerNumber(?string $customerNumber): self
    {
        $this->customerNumber = $customerNumber;

        return $this;
    }

    public function setCardIssuerBank(?string $cardIssuerBank): self
    {
        $this->cardIssuerBank = $cardIssuerBank;

        return $this;
    }

    public function setCustomerName(?string $customerName): self
    {
        $this->customerName = $customerName;

        return $this;
    }

    public function setCustomerEmail(?string $customerEmail): self
    {
        $this->customerEmail = $customerEmail;

        return $this;
    }

    public function setCustomerPhone(?string $customerPhone): self
    {
        $this->customerPhone = $customerPhone;

        return $this;
    }

    public function setBankId(?int $bankId): self
    {
        $this->bankId = $bankId;

        return $this;
    }

    public function setCreatedAt(?string $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function setUpdatedAt(?string $updatedAt): self
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCardToken(): ?string
    {
        return $this->cardToken;
    }

    public function getCardUserKey(): ?string
    {
        return $this->cardUserKey;
    }

    public function getCardNumber(): ?string
    {
        return $this->cardNumber;
    }

    public function getMerchantId(): ?int
    {
        return $this->merchantId;
    }

    public function getCustomerNumber(): ?string
    {
        return $this->customerNumber;
    }

    public function getCardIssuerBank(): ?string
    {
        return $this->cardIssuerBank;
    }

    public function getCustomerName(): ?string
    {
        return $this->customerName;
    }

    public function getCustomerEmail(): ?string
    {
        return $this->customerEmail;
    }

    public function getCustomerPhone(): ?string
    {
        return $this->customerPhone;
    }

    public function getBankId(): ?int
    {
        return $this->bankId;
    }

    public function getCreatedAt(): ?string
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?string
    {
        return $this->updatedAt;
    }
}
