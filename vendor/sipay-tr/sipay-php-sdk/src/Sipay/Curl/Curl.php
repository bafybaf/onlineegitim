<?php

declare(strict_types=1);

namespace Sipay\Curl;

use Sipay\Exceptions\ApiConnectionException;
use Sipay\Utils\CaseInsensitiveArray;

class Curl
{
    public static function instance(): self
    {
        return new self();
    }

    public function exec($url, $options): CurlResponse
    {
        $responseCode = 0;
        $errno = 0;
        $message = null;

        $responseHeaders = new CaseInsensitiveArray();
        $headerCallback = function ($curl, $headerLine) use (&$responseHeaders) {
            return self::parseLineIntoHeaderArray($headerLine, $responseHeaders);
        };

        $options[\CURLOPT_HEADERFUNCTION] = $headerCallback;

        $ch = curl_init($url);

        curl_setopt_array($ch, $options);

        $responseBody = curl_exec($ch);

        if (false === $responseBody) {
            $errno = \curl_errno($ch);
            $message = \curl_error($ch);
        } else {
            $responseCode = \curl_getinfo($ch, \CURLINFO_HTTP_CODE);
        }

        if (false === $responseBody) {
            $this->handleCurlError($url, $errno, $message);
        }

        return CurlResponse::create($responseBody, $responseCode, $responseHeaders);
    }

    private function handleCurlError($url, $errno, $message, $numRetries = 0): void
    {
        switch ($errno) {
            case \CURLE_COULDNT_CONNECT:
            case \CURLE_COULDNT_RESOLVE_HOST:
            case \CURLE_OPERATION_TIMEOUTED:
                $msg = "Could not connect to Sipay ({$url}).  Please check your "
                    . 'internet connection and try again.';

                break;

            case \CURLE_SSL_CACERT:
            case \CURLE_SSL_PEER_CERTIFICATE:
                $msg = "Could not verify Sipay's SSL certificate.  Please make sure "
                    . 'that your network is not intercepting certificates.  '
                    . "(Try going to {$url} in your browser.)  ";

                break;

            default:
                $msg = 'Unexpected error communicating with Sipay.  ';
        }

        $msg .= "\n\n(Network error [errno {$errno}]: {$message})";

        if ($numRetries > 0) {
            $msg .= "\n\nRequest was retried {$numRetries} times.";
        }

        throw new ApiConnectionException($msg);
    }

    private function toShellCommand($url, $options): string
    {
        $command = "curl '{$url}'";

        foreach ($options as $key => $value) {
            switch ($key) {
                case CURLOPT_HTTPHEADER:
                    foreach ($value as $header) {
                        $command .= " -H '{$header}'";
                    }

                    break;
                case CURLOPT_CUSTOMREQUEST:
                    $command .= " -X {$value}";

                    break;
                case CURLOPT_POSTFIELDS:
                    if (is_array($value)) {
                        $value = json_encode($value);
                    }
                    $command .= " -d '{$value}'";

                    break;
                case CURLOPT_SSL_VERIFYPEER:
                    if (!$value) {
                        $command .= " -k";
                    }

                    break;
            }
        }

        return $command;
    }

    private static function parseLineIntoHeaderArray($line, &$headers)
    {
        if (false === \strpos($line, ':')) {
            return \strlen($line);
        }
        [$key, $value] = \explode(':', \trim($line), 2);
        $headers[\trim($key)] = \trim($value);

        return \strlen($line);
    }
}
