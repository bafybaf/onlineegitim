<?php

declare(strict_types=1);

namespace Sipay\Resources\Mapper;

use Sipay\Resources\Mapper\Contracts\HtmlMapper;
use Sipay\Resources\SavedCardPayment;

/**
 * @property SavedCardPayment $resource
 */
class SavedCardPaymentMapper extends SipayResourceMapper implements HtmlMapper
{
    public function mapHtmlBody(): SavedCardPayment
    {
        // HTML form yanıtını doğrudan set ediyoruz
        if (isset($this->htmlBody)) {
            $this->resource->setHtmlForm($this->htmlBody);
        }

        return $this->resource;
    }
}
