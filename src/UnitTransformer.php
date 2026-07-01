<?php

declare(strict_types=1);

namespace Bakame\Tokei;

use function ceil;
use function floor;
use function intdiv;
use function round;

use const PHP_INT_MAX;
use const PHP_INT_MIN;

/**
 * @internal class to convert between time units
 */
final readonly class UnitTransformer
{
    private function __construct()
    {
    }

    public static function toMicroseconds(int|float $value, Unit $unit): int
    {
        $micro = $unit->inMicroseconds();

        ($value <= intdiv(PHP_INT_MAX, $micro) && $value >= intdiv(PHP_INT_MIN, $micro)) || throw InvalidDuration::dueToOverflow();

        return (int) round($micro * $value);
    }

    public static function fromMicroseconds(int $valueInMicro, Unit $unit): int|float
    {
        return $valueInMicro / $unit->inMicroseconds();
    }

    /**
     * @return array{0: int, 1: int}
     */
    public static function divmod(int $valueInMicro, Unit $unit): array
    {
        $micro = $unit->inMicroseconds();

        return [intdiv($valueInMicro, $micro), $valueInMicro % $micro];
    }

    public static function round(int $valueInMicro, Unit $unit, SnapMode $mode = SnapMode::Nearest): int
    {
        $micro = $unit->inMicroseconds();

        return (int) ($micro * match ($mode) {
            SnapMode::Floor => floor($valueInMicro / $micro),
            SnapMode::Ceil => ceil($valueInMicro / $micro),
            SnapMode::Nearest => round($valueInMicro / $micro),
        });
    }

    /**
     * @return non-negative-int
     */
    public static function wrap(int $valueInMicro, Unit $unit): int
    {
        $micro = $unit->inMicroseconds();

        /** @var non-negative-int $value */
        $value = ($valueInMicro % $micro + $micro) % $micro;

        return $value;
    }
}
