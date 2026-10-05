<?php

declare(strict_types=1);

namespace Sipay\Models;

/*
 * Kayıtlı Kartla Ödeme Yapma
 */

use Sipay\Models\Concerns\InteractsWithSavedCard;

class SavedCardPaymentInvoice extends PaymentInvoice
{
    use InteractsWithSavedCard;

    public function getJsonObject(bool $encodeItems = true)
    {
        return parent::getJsonObject($encodeItems);
    }
}
