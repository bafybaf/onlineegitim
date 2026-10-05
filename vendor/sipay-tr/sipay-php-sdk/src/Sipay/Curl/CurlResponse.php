<?php

declare(strict_types=1);

namespace Sipay\Curl;

class CurlResponse
{
    private $body;

    private $statusCode;

    private $headers;

    public function __construct($body, $code, $headers)
    {
        $this->body = $body;
        $this->statusCode = $code;
        $this->headers = $headers;
    }

    public static function create($body, $code, $headers)
    {
        return new static($body, $code, $headers);
    }

    // Getter metodları
    public function getBody()
    {
        return $this->body;
    }

    public function getStatusCode()
    {
        return $this->statusCode;
    }

    public function getHeaders()
    {
        return $this->headers;
    }
}
