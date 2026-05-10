<?php

declare(strict_types=1);

namespace Bakame\Tokei;

class InvalidTime extends TimeException
{
    public static function dueToMalformedHour(int $hour): self
    {
        return new self("Hour must be between 0 and 23, got {$hour}.");
    }

    public static function dueToMalformedMinute(int $minute): self
    {
        return new self("Minute must be between 0 and 59, got {$minute}.");
    }

    public static function dueToMalformedSecond(int $second): self
    {
        return new self("Second must be between 0 and 59, got {$second}.");
    }

    public static function dueToMalformedMicrosecond(int $microsecond): self
    {
        return new self("Microsecond must be between 0 and 999999, got {$microsecond}.");
    }

    public static function dueToInvalidSeparator(string $separator): self
    {
        return new self("Time separator must be a single non-digit character, got {$separator}.");
    }
}
