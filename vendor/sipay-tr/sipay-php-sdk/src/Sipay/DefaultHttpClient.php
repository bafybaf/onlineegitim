<?php

declare(strict_types=1);

namespace Sipay;

use Sipay\Contracts\HttpClient;
use Sipay\Curl\Curl;

class DefaultHttpClient implements HttpClient
{
    private $curl;

    public function __construct($curl = null)
    {
        if (!$curl) {
            $curl = Curl::instance();
        }

        $this->curl = $curl;
    }

    public static function instance($curl = null): self
    {
        return new self($curl);
    }

    public function get(string $url, array $header = [], $content = null)
    {
        $options = [
            CURLOPT_CUSTOMREQUEST => "GET",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_VERBOSE => false,
            CURLOPT_HEADER => false,
            CURLOPT_ENCODING => "",
        ];

        if ($header !== []) {
            $options[CURLOPT_HTTPHEADER] = $header;
        }

        if ($content !== null) {
            $options[CURLOPT_POSTFIELDS] = is_array($content) ? json_encode($content) : $content;
        }

        return $this->curl->exec($url, $options);
    }

    public function post($url, $header, $content)
    {
        $options = [
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => is_array($content) ? json_encode($content) : $content,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_VERBOSE => false,
            CURLOPT_HEADER => false,
            CURLOPT_HTTPHEADER => $header,
            CURLOPT_ENCODING => "",
        ];

        return $this->curl->exec($url, $options);
    }

    public function put($url, $header, $content)
    {
        return $this->curl->exec($url, [
            CURLOPT_CUSTOMREQUEST => "PUT",
            CURLOPT_POSTFIELDS => $content,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_VERBOSE => false,
            CURLOPT_HEADER => false,
            CURLOPT_HTTPHEADER => $header,
        ]);
    }

    public function patch($url, $header, $content)
    {
        return $this->curl->exec($url, [
            CURLOPT_CUSTOMREQUEST => "PATCH",
            CURLOPT_POSTFIELDS => $content,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_VERBOSE => false,
            CURLOPT_HEADER => false,
            CURLOPT_HTTPHEADER => $header,
        ]);
    }

    public function delete($url, $header, $content = null)
    {
        return $this->curl->exec($url, [
            CURLOPT_CUSTOMREQUEST => "DELETE",
            CURLOPT_POSTFIELDS => $content,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_VERBOSE => false,
            CURLOPT_HEADER => false,
            CURLOPT_HTTPHEADER => $header,
        ]);
    }
}
