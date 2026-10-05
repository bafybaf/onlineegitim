<?php

declare(strict_types=1);

namespace Sipay\Models\Concerns;

trait InteractsWithSavedCard
{
    public function setSavedCard($cardToken, $customerNumber, $customerEmail, $customerPhone, $customerName): self
    {
        $this->cardToken = $cardToken;
        $this->customerNumber = $customerNumber;
        $this->customerEmail = $customerEmail;
        $this->customerPhone = $customerPhone;
        $this->customerName = $customerName;

        return $this;
    }
}
