<?php

declare(strict_types=1);

namespace Sipay\Enums;

final class PaymentMethod
{
    public const CREDIT_CARD = 1;
    public const MOBIlE = 2;
    public const WALLET = 3;

    public const DESCRIPTIONS = [
        self::CREDIT_CARD => 'Credit Card',
        self::MOBIlE => 'Mobile',
        self::WALLET => 'Wallet',
    ];

    public static function getDescription(int $paymentMethod): ?string
    {
        return self::DESCRIPTIONS[$paymentMethod] ?? null;
    }
}
