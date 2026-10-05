<?php

declare(strict_types=1);

namespace Sipay\Resources\Concerns;

use Sipay\Enums\PaymentMethod;

trait InteractsPaymentMethod
{
    public function getPaymentMethodDescription(): ?string
    {
        if (!$this->paymentMethod && !is_numeric($this->paymentMethod)) {
            return null;
        }

        return PaymentMethod::getDescription((int) $this->paymentMethod);
    }
}
