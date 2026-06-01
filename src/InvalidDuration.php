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

    public static function dueToMalformedIso8601(string $format): self
    {
        $containsUnsupportedUnits = str_contains($format, 'Y') || self::containsMonthComponent($format);

        $message = $containsUnsupportedUnits
            ? "The submitted duration `$format` contains unsupported ISO 8601 duration components."
            : "The submitted duration `$format` is not a valid ISO 8601 duration.";

        return new self($message);
    }

    private static function containsMonthComponent(string $data): bool
    {
        $monthPosition = strpos($data, 'M');
        if (false === $monthPosition) {
            return false;
        }

        $timePosition = strpos($data, 'T');

        return false === $timePosition || $monthPosition < $timePosition;
    }

    public static function dueToMalformedMinute(int $minute): self
    {
        return new self("Minute must be between 0 and 59, got $minute.");
    }

    public static function dueToMalformedSecond(int $second): self
    {
        return new self("Second must be between 0 and 59, got $second.");
    }

    public static function dueToMalformedMicrosecond(int $microsecond): self
    {
        return new self("Microsecond must be between 0 and 999999, got $microsecond.");
    }
}
