<?php

declare(strict_types=1);

namespace Bakame\Tokei;

use DateTimeZone;
use Throwable;

class TimeException extends TokeiException
{
    public static function dueToInvalidTimezone(string $timezone, ?Throwable $previous = null): static
    {
        return new static(
            message: 'Timezone must be a valid IANA Timezone Identifier supported by '.DateTimeZone::class.'; '.$timezone.' given.',
            previous: $previous,
        );
    }

    public static function dueToMalformedHour(int $hour): static
    {
        return new static("Hour must be between 0 and 23, got $hour.");
    }

    public static function dueToMalformedMinute(int $minute): static
    {
        return new static("Minute must be between 0 and 59, got $minute.");
    }

    public static function dueToMalformedSecond(int $second): static
    {
        return new static("Second must be between 0 and 59, got $second.");
    }

    public static function dueToMalformedMicrosecond(int $microsecond): static
    {
        return new static("Microsecond must be between 0 and 999999, got $microsecond.");
    }
}
