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

    public static function dueToMalformedTime(int $value, Unit $unit): static
    {
        $prefix = $unit->name.' must be ';

        return new static(match ($unit) {
            Unit::Week,
            Unit::Day => $prefix.' greater than or equal to 0',
            Unit::Hour => $prefix.' between 0 and 23',
            Unit::Minute,
            Unit::Second => $prefix.' between 0 and 59',
            Unit::Millisecond => $prefix.' must be between 0 and 999',
            Unit::Microsecond => $prefix.' must be between 0 and 999999',
        }.", got $value.");
    }
}
