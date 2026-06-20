<?php

declare(strict_types=1);

namespace Bakame\Tokei;

use function ceil;
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
        $factor = $unit->inMicroseconds();
        ($value <= intdiv(PHP_INT_MAX, $factor) && $value >= intdiv(PHP_INT_MIN, $factor)) || throw InvalidDuration::dueToOverflow();

        return (int) round($factor * $value);
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
}
