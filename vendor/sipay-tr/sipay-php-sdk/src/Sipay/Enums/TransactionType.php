<?php

declare(strict_types=1);

namespace Sipay\Enums;

final class TransactionType
{
    public const AUTH = 'Auth'; // // işlem tutarı anında karttan düşülür,
    public const PRE_AUTHORIZATION = 'PreAuth'; // işlem tutarı daha sonra karttan düşülecektir.
}
