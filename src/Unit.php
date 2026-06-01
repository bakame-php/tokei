<?php

declare(strict_types=1);

namespace Bakame\Tokei;

enum Unit
{
    case Week;
    case Day;
    case Hour;
    case Minute;
    case Second;
    case Millisecond;
    case Microsecond;

    public function inMicroseconds(): int
    {
        return match ($this) {
            Unit::Week => 86_400_000_000 * 7,
            Unit::Day => 86_400_000_000,
            Unit::Hour => 3_600_000_000,
            Unit::Minute => 60_000_000,
            Unit::Second => 1_000_000,
            Unit::Millisecond => 1_000,
            Unit::Microsecond => 1,
        };
    }
}
