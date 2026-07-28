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

    public static function ticks(Unit $unit): int
    {
        return match ($unit) {
            Unit::Week => 604_800_000_000_000,
            Unit::Day => 86_400_000_000_000,
            Unit::Hour => 3_600_000_000_000,
            Unit::Minute => 60_000_000_000,
            Unit::Second => 1_000_000_000,
            Unit::Millisecond => 1_000_000,
            Unit::Microsecond => 1_000,
            Unit::Nanosecond => 1,
        };
    }

    public static function fromTicks(int $valueInTicks, Unit $unit): int|float
    {
        return $valueInTicks / self::ticks($unit);
    }

    /**
     * @throws InvalidDuration
     */
    public static function toTicks(int|float $value, Unit $unit): int
    {
        $ticks = self::ticks($unit);

        ($value <= intdiv(PHP_INT_MAX, $ticks) && $value >= intdiv(PHP_INT_MIN, $ticks)) || throw InvalidDuration::dueToOverflow();

        return (int) round($ticks * $value);
    }

    public static function convert(int|float $value, Unit $from, Unit $to): int|float
    {
        return self::fromTicks(self::toTicks($value, $from), $to);
    }

    /**
     * @throws InvalidDuration
     */
    public static function add(int $left, int $right): int
    {
        ($right <= PHP_INT_MAX - $left) || throw InvalidDuration::dueToOverflow();

        return $left + $right;
    }

    /**
     * @return array{0: int, 1: int}
     */
    public static function divmod(int $valueInMicro, Unit $unit): array
    {
        $ticks = self::ticks($unit);

        return [intdiv($valueInMicro, $ticks), $valueInMicro % $ticks];
    }

    public static function round(int $valueInMicro, Unit $unit, SnapMode $mode = SnapMode::Nearest): int
    {
        $ticks = self::ticks($unit);

        return (int) ($ticks * match ($mode) {
            SnapMode::Floor => floor($valueInMicro / $ticks),
            SnapMode::Ceil => ceil($valueInMicro / $ticks),
            SnapMode::Nearest => round($valueInMicro / $ticks),
        });
    }

    /**
     * @return non-negative-int
     */
    public static function wrap(int $valueInTicks, Unit $unit): int
    {
        $ticks = self::ticks($unit);

        /** @var non-negative-int $value */
        $value = ($valueInTicks % $ticks + $ticks) % $ticks;

        return $value;
    }
}
