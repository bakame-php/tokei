<?php

declare(strict_types=1);

namespace Bakame\Tokei;

use function intdiv;
use function is_float;
use function round;

enum Unit
{
    case Week;
    case Day;
    case Hour;
    case Minute;
    case Second;
    case Millisecond;
    case Microsecond;

    private const int MICRO_PER_MILLI = 1_000;
    private const int MICRO_PER_SECOND = 1_000 * self::MICRO_PER_MILLI;
    private const int MICRO_PER_MINUTE = 60 * self::MICRO_PER_SECOND;
    private const int MICRO_PER_HOUR = 60 * self::MICRO_PER_MINUTE;
    private const int MICRO_PER_DAY = 24 * self::MICRO_PER_HOUR;
    private const int MICRO_PER_WEEK = 7 * self::MICRO_PER_DAY;

    public function microseconds(): int
    {
        return match ($this) {
            Unit::Week => self::MICRO_PER_WEEK,
            Unit::Day => self::MICRO_PER_DAY,
            Unit::Hour => self::MICRO_PER_HOUR,
            Unit::Minute => self::MICRO_PER_MINUTE,
            Unit::Second => self::MICRO_PER_SECOND,
            Unit::Millisecond => self::MICRO_PER_MILLI,
            Unit::Microsecond => 1,
        };
    }

    public function round(int $valueInMicro): int
    {
        $unit = $this->microseconds();

        return intdiv($valueInMicro + intdiv($unit, 2), $unit) * $unit;
    }

    public function truncate(int $valueInMicro): int
    {
        $unit = $this->microseconds();

        return intdiv($valueInMicro, $unit) * $unit;
    }

    public function toMicroseconds(int|float $value): int
    {
        $res = $this->microseconds() * $value;

        return is_float($res) ? (int) round($res) : $res;
    }

    public function whole(int $value): int
    {
        return intdiv($value, $this->microseconds());
    }

    public function remainder(int $value): int
    {
        return $value % $this->microseconds();
    }

    public function divide(int $value): int|float
    {
        return $value / $this->microseconds();
    }

    public function wrap(int $valueInMicro): int
    {
        $unit = $this->microseconds();

        return (($valueInMicro % $unit) + $unit) % $unit;
    }
}
