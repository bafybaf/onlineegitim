<?php

declare(strict_types=1);

namespace Sipay\Contracts;

interface HttpClient
{
    public function get(string $url, array $header = [], ?array $content = null);

    public function post($url, $header, $content);

    public function put($url, $header, $content);

    public function delete($url, $header, $content = null);
}
