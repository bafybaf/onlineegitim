<?php

declare(strict_types=1);

namespace Sipay;

class SipayOptions
{
    public const API_BASE_URL = 'https://app.sipay.com.tr/ccpayment';

    private $apiKey;

    private $apiSecret;

    private $apiBaseUrl = self::API_BASE_URL;

    private $merchantKey;

    private $merchantId;

    public function __construct(
        ?string $apiKey = null,
        ?string $apiSecret = null,
        ?string $merchantKey = null,
        ?string $merchantId = null,
        ?string $apiBaseUrl = null
    ) {
        $this->apiKey = $apiKey;
        $this->apiSecret = $apiSecret;
        $this->merchantKey = $merchantKey;
        $this->merchantId = $merchantId;
        if ($apiBaseUrl !== null) {
            $this->apiBaseUrl = $apiBaseUrl;
        }
    }

    public static function create(array $options = []): self
    {
        return new self(
            $options['apiKey'] ?? null,
            $options['secretKey'] ?? null,
            $options['merchantKey'] ?? null,
            $options['merchantId'] ?? null,
            $options['baseUrl'] ?? null,
        );
    }

    public function getApiKey(): ?string
    {
        return $this->apiKey;
    }

    public function setApiKey($apiKey): self
    {
        $this->apiKey = $apiKey;

        return $this;
    }

    public function getApiSecret(): ?string
    {
        return $this->apiSecret;
    }

    public function setApiSecret($apiSecret): self
    {
        $this->apiSecret = $apiSecret;

        return $this;
    }

    public function getApiBaseUrl(): string
    {
        return $this->apiBaseUrl;
    }

    public function setApiBaseUrl(?string $apiBaseUrl): self
    {
        $this->apiBaseUrl = $apiBaseUrl;

        return $this;
    }

    public function getMerchantKey(): ?string
    {
        return $this->merchantKey;
    }

    public function setMerchantKey(?string $merchantKey): self
    {
        $this->merchantKey = $merchantKey;

        return $this;
    }

    public function getMerchantId(): ?string
    {
        return $this->merchantId;
    }

    public function setMerchantId(?string $merchantId): self
    {
        $this->merchantId = $merchantId;

        return $this;
    }

    public function toArray(): array
    {
        return [
            'apiKey' => $this->apiKey,
            'apiSecret' => $this->apiSecret,
            'merchantKey' => $this->merchantKey,
            'merchantId' => $this->merchantId,
            'apiBaseUrl' => $this->apiBaseUrl,
        ];
    }
}
