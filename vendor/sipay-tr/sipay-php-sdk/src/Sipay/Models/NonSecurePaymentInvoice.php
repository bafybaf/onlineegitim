<?php

declare(strict_types=1);

namespace Sipay\Models;

use Sipay\Models\Concerns\InteractsWithNewCard;

/**
 * 2D Ödeme.
 */
class NonSecurePaymentInvoice extends PaymentInvoice
{
    use InteractsWithNewCard;

    public function getJsonObject(bool $encodeItems = false)
    {
        return parent::getJsonObject();
    }
}
