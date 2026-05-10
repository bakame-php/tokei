<?php

declare(strict_types=1);

namespace Bakame\Tokei;

use function str_contains;
use function strpos;

class InvalidDuration extends TimeException
{
    public static function dueToOverflow(): self
    {
        return new self('The duration exceeds the supported range.');
    }

    public static function dueToMalformedIso8601(string $notation): self
    {
        $containsUnsupportedUnits = str_contains($notation, 'Y') || self::containsMonthComponent($notation);

        $message = $containsUnsupportedUnits
            ? "The submitted duration `$notation` contains unsupported ISO 8601 duration components."
            : "The submitted duration `$notation` is not a valid ISO 8601 duration.";

        return new self($message);
    }

    private static function containsMonthComponent(string $value): bool
    {
        $monthPosition = strpos($value, 'M');
        if (false === $monthPosition) {
            return false;
        }

        $timePosition = strpos($value, 'T');

        return false === $timePosition || $monthPosition < $timePosition;
    }
}
