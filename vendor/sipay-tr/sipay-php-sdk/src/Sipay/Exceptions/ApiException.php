<?php

declare(strict_types=1);

namespace Sipay\Exceptions;

use Exception;
use Sipay\Enums\ResponseStatusCode;

abstract class ApiException extends Exception implements SipayExceptionInterface
{
    protected $httpBody;

    protected $httpHeaders;

    protected $httpStatus;

    protected $jsonBody;

    protected $sipayStatusCode;

    public static function createException(
        string  $message,
        ?int    $httpStatus = null,
        ?string $httpBody = null,
        ?array   $jsonBody = null,
        $httpHeaders = null,
        ?int $responseStatusCode = null
    ): self {
        $instance = new static($message);
        $instance->setHttpStatus($httpStatus);
        $instance->setHttpBody($httpBody);
        $instance->setJsonBody($jsonBody);
        $instance->setHttpHeaders($httpHeaders);
        $instance->setSipayStatusCode($responseStatusCode);

        return $instance;
    }

    public function getHttpBody()
    {
        return $this->httpBody;
    }

    public function setHttpBody($httpBody): self
    {
        $this->httpBody = $httpBody;

        return $this;
    }

    public function getHttpHeaders()
    {
        return $this->httpHeaders;
    }

    public function setHttpHeaders($httpHeaders): self
    {
        $this->httpHeaders = $httpHeaders;

        return $this;
    }

    public function getHttpStatus()
    {
        return $this->httpStatus;
    }

    public function setHttpStatus($httpStatus): self
    {
        $this->httpStatus = $httpStatus;

        return $this;
    }

    public function getJsonBody()
    {
        return $this->jsonBody;
    }

    public function setJsonBody($jsonBody): self
    {
        $this->jsonBody = $jsonBody;

        return $this;
    }

    public function getSipayStatusCode()
    {
        return $this->sipayStatusCode;
    }

    public function setSipayStatusCode($sipayStatusCode): self
    {
        $this->sipayStatusCode = $sipayStatusCode;

        return $this;
    }

    public function getSipayStatusCodeDescription(): ?string
    {
        if (!is_numeric($this->sipayStatusCode)) {
            return null;
        }

        return ResponseStatusCode::getDescription($this->sipayStatusCode);
    }

    public function __toString()
    {
        $parentStr = parent::__toString();
        $statusStr = (null === $this->getHttpStatus()) ? '' : "(Http Status: {$this->getHttpStatus()}) ";
        $idStr = (null === $this->getSipayStatusCodeDescription()) ? '' : "(Sipay Status Code Description: {$this->getSipayStatusCodeDescription()}) ";
        $messageStr = (null === $this->getMessage()) ? '' : "(Message: {$this->getMessage()}) ";

        return "Error sending request to Sipay API: {$statusStr}{$idStr}{$messageStr}\n{$parentStr}";
    }
}
