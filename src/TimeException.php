<?php

declare(strict_types=1);

namespace Bakame\Tokei;

use DateTimeZone;
use Exception;
use Throwable;

class TimeException extends Exception
{
    public static function invalidTimezone(string $timezone, ?Throwable $previous = null): self
    {
        return new self(
            message: 'Timezone must be a valid IANA Timezone Identifier supported by '.DateTimeZone::class.'; '.$timezone.' given.',
            previous: $previous,
        );
    }
}
