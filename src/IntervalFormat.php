<?php

declare(strict_types=1);

namespace Bakame\Tokei;

/**
 * @see https://en.wikipedia.org/wiki/Interval_(mathematics)#Notations_for_intervals
 * @see https://en.wikipedia.org/wiki/ISO_31-11
 */
enum IntervalFormat
{
    case Iso8601StartDuration;
    case Iso8601DurationEnd;
    case Iso8601StartEnd;
    case Iso80000;
    case Bourbaki;

    public function supportsUnit(): bool
    {
        return match ($this) {
            self::Iso80000,
            self::Bourbaki => true,
            default => false,
        };
    }
}
