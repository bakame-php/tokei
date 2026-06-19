<?php

declare(strict_types=1);

namespace Bakame\Tokei;

use function ceil;
use function compact;
use function floor;
use function intdiv;
use function round;

/**
 * @internal class to convert between time units
 */
final class UnitTransformer
{
    public static function round(int $valueInMicro, Unit $unit, SnapMode $mode = SnapMode::Nearest): int
    {
        $unit = $unit->inMicroseconds();
        $factor = $valueInMicro / $unit;
        $roundedFactor = match ($mode) {
            SnapMode::Floor => floor($factor),
            SnapMode::Ceil => ceil($factor),
            SnapMode::Nearest => round($factor),
        };

        return (int) ($roundedFactor * $unit);
    }

    public static function toMicroseconds(int|float $value, Unit $unit): int
    {
        return (int) round($unit->inMicroseconds() * $value);
    }

    public static function fromMicroseconds(int $valueInMicro, Unit $unit): int|float
    {
        return $valueInMicro / $unit->inMicroseconds();
    }

    public static function whole(int $valueInMicro, Unit $unit): int
    {
        return intdiv($valueInMicro, $unit->inMicroseconds());
    }

    public static function remainder(int $valueInMicro, Unit $unit): int
    {
        return $valueInMicro % $unit->inMicroseconds();
    }

    public static function wrap(int $valueInMicro, Unit $unit): int
    {
        $micro = $unit->inMicroseconds();

        return ($valueInMicro % $micro + $micro) % $micro;
    }

    public static function compose(
        int $days,
        int $hours,
        int $minutes,
        int|float $seconds,
        int $microseconds,
        int $sign,
    ): int {
        $value = self::toMicroseconds($days, Unit::Day)
            + self::toMicroseconds($hours, Unit::Hour)
            + self::toMicroseconds($minutes, Unit::Minute)
            + self::toMicroseconds($seconds, Unit::Second)
            + $microseconds;

        return $value * $sign;
    }

    /**
     * @return object{
     *     weeksCount: int,
     *     daysCount: int,
     *     hours: int,
     *     minutes: int,
     *     seconds: int,
     *     microseconds: int,
     *     sign: int<-1, 1>,
     * }
     */
    public static function decompose(int $value): object
    {
        $sign = $value <=> 0 ;
        $microseconds = -1 === $sign ? -$value : $value;
        $weeksCount = UnitTransformer::whole($microseconds, Unit::Week);
        $daysCount = UnitTransformer::whole($microseconds, Unit::Day);
        $hours = UnitTransformer::whole($microseconds, Unit::Hour);
        $microseconds = UnitTransformer::remainder($microseconds, Unit::Hour);
        $minutes = UnitTransformer::whole($microseconds, Unit::Minute);
        $microseconds = UnitTransformer::remainder($microseconds, Unit::Minute);
        $seconds = UnitTransformer::whole($microseconds, Unit::Second);
        $microseconds = UnitTransformer::remainder($microseconds, Unit::Second);

        return (object) compact(
            'weeksCount',
            'daysCount',
            'hours',
            'minutes',
            'seconds',
            'microseconds',
            'sign',
        );
    }
}
