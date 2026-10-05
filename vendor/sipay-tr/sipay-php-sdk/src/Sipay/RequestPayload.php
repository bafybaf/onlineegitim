<?php

declare(strict_types=1);

namespace Sipay;

use Sipay\Concerns\CanConvertToJson;
use Sipay\Contracts\JsonConvertible;
use Sipay\Contracts\RequestStringConvertible;

abstract class RequestPayload implements JsonConvertible, RequestStringConvertible
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

    public function getMerchantKey()
    {
        return $this->getOptions()->getMerchantKey();
    }

    public function getApiKey()
    {
        return $this->getOptions()->getApiKey();
    }

    public function getApiSecret()
    {
        return $this->getOptions()->getApiSecret();
    }

    public function toPKIRequestString()
    {
        return RequestStringBuilder::create()
            ->getRequestString();
    }
}
