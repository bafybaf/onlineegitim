<?php

declare(strict_types=1);

namespace Sipay\Models\Concerns;

trait InteractsWithNewCard
{
    public function setNewCard($ccHolderName, $ccNo, $expiryMonth, $expiryYear, $cvv): self
    {
        $this->ccHolderName = $ccHolderName;
        $this->ccNo = $ccNo;
        $this->expiryMonth = $expiryMonth;
        $this->expiryYear = $expiryYear;
        $this->cvv = $cvv;

        return $this;
    }
}
