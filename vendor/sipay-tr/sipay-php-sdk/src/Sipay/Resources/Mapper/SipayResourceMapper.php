<?php

declare(strict_types=1);

namespace Sipay\Resources\Mapper;

use Sipay\Enums\ResponseStatusCode;
use Sipay\Exceptions\InvalidArgumentException;
use Sipay\JsonBuilder;
use Sipay\Resources\Mapper\Contracts\HtmlMapper;
use Sipay\Resources\Mapper\Contracts\JsonMapper;
use Sipay\SipayResource;

abstract class SipayResourceMapper
{
    protected $rawResult;

    protected $jsonBody;

    protected $htmlBody;

    protected $resource;

    public function __construct($rawResult, SipayResource $resource)
    {
        $this->rawResult = $rawResult;
        $this->resource = $resource;

        if ($this instanceof JsonMapper) {
            $this->toJsonBody();
            $this->initMapJsonBody();
        } elseif ($this instanceof HtmlMapper) {
            $this->toHtmlBody();
        } else {
            throw new InvalidArgumentException('Mapper must implement JsonMapper or HtmlMapper');
        }
    }

    public static function create($rawResult, SipayResource $resource): self
    {
        return new static($rawResult, $resource);
    }

    public function toJsonBody(): self
    {
        $this->jsonBody = JsonBuilder::jsonDecode($this->rawResult);

        return $this;
    }

    public function toHtmlBody(): self
    {
        $this->htmlBody = $this->rawResult;

        return $this;
    }

    protected function initMapJsonBody(): void
    {
        if (isset($this->jsonBody->status_code)) {
            $this->resource->setStatusCode($this->jsonBody->status_code);

            $statusCodeDescription = ResponseStatusCode::getDescription($this->jsonBody->status_code);

            if ($statusCodeDescription) {
                $this->resource->setStatusCodeDescription($statusCodeDescription);
            }
        }

        if (isset($this->jsonBody->status_description)) {
            $this->resource->setStatusDescription($this->jsonBody->status_description);
        } elseif (isset($this->jsonBody->message)) {
            $this->resource->setStatusDescription($this->jsonBody->message);
        }
    }
}
