<?php

declare(strict_types=1);

namespace Sipay;

use JsonSerializable;
use Sipay\Concerns\CanConvertToJson;
use Sipay\Contracts\JsonConvertible;

abstract class Model implements JsonConvertible, JsonSerializable
{
    use CanConvertToJson;

    private $options;

    public function __construct(SipayOptions $options)
    {
        $this->setOptions($options);
    }

    public function getOptions(): SipayOptions
    {
        return $this->options;
    }

    public function setOptions(SipayOptions $options): self
    {
        $this->options = $options;

        return $this;
    }

    public function getMerchantKey(): ?string
    {
        return $this->getOptions()->getMerchantKey();
    }

    public function getApiSecret(): ?string
    {
        return $this->getOptions()->getApiSecret();
    }

    public function jsonSerialize()
    {
        return $this->getJsonObject();
    }
}
