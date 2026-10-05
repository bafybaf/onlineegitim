<?php

declare(strict_types=1);

namespace Sipay\Utils;

class RedactString
{
    public static function redact(?string $value, int $visibleLength = 5): string
    {
        if (null === $value) {
            return '';
        }

        $valueLength = strlen($value);

        if ($valueLength <= 10) {
            $halfVisible = (int)floor($visibleLength / 2);
            $hiddenLength = $valueLength - (2 * $halfVisible);

            return substr($value, 0, $halfVisible)
                . str_repeat('*', $hiddenLength)
                . substr($value, -$halfVisible);
        }

        $hiddenLength = $valueLength - (2 * $visibleLength);

        return substr($value, 0, $visibleLength)
            . str_repeat('*', $hiddenLength)
            . substr($value, -$visibleLength);
    }
}
