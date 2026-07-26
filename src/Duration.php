<?php

declare(strict_types=1);

namespace Bakame\Tokei;

use ArgumentCountError;
use DateInterval;
use DateTimeInterface;
use DivisionByZeroError;
use JsonSerializable;
use Time\Duration as TimeDuration;
use ValueError;

use function abs;
use function array_key_first;
use function array_key_last;
use function array_map;
use function intdiv;
use function is_int;
use function number_format;
use function preg_match;
use function str_pad;
use function usort;

use const PHP_INT_MAX;
use const PHP_INT_MIN;

/**
 * @phpstan-type InputDurationPart ''|numeric-string|int
 * @phpstan-type InputSign ''|'+'|'-'
 */
final class Duration implements JsonSerializable
{
    private const string REGEXP_TIMER = '@^
        (?<sign>-)?\s*
        (?<hours>\d+):
        (?<minutes>\d{1,2}):
        (?<seconds>\d{1,2})
        ((\.|")(?<fractions>\d{1,9}))?
    $@x';

    private const string REGEXP_COMPACT = '@^
        (?<sign>-)?\s*
        (?:(?<weeks>\d+)\s*w\s*)?
        (?:(?<days>\d+)\s*d\s*)?
        (?:(?<hours>\d+)\s*h\s*)?
        (?:(?<minutes>\d+)\s*m\s*)?
        (?:(?<seconds>\d+)\s*s\s*)?
        (?:
            (?<fractions>\d{1,9})\s*
            (?<unit>µs|us|ms|ns)\s*
        )?
    $@x';

    private const string REGEXP_ISO8601 = '@^
        (?<sign>[+-])?
        P
        (?=.*?(?:\d+W|\d+D|T\d+H|T\d+M|T\d+(?:[\.,]\d+)?S))
        (?:(?<weeks>\d+)W)?
        (?:(?<days>\d+)D)?
        (?:T
            (?:(?<hours>\d+)H)?
            (?:(?<minutes>\d+)M)?
            (?:
                (?<seconds>\d+)
                (?:[\.,](?<fractions>\d{1,9}))?
                S
            )?
        )?
    $@x';

    private const int MICRO_TO_SECONDS = 1_000_000;

    private ?DurationParts $parts = null;
    private readonly int $ticks;
    public readonly int $sign;
    public readonly int $seconds;
    public readonly int $microseconds;

    /**
     * @param int $ticks expressed in microseconds
     *
     * @throws TokeiException
     */
    private function __construct(int $ticks)
    {
        PHP_INT_MIN !== $ticks || throw InvalidDuration::dueToOverflow();

        $this->ticks = $ticks;
        $this->sign = $ticks <=> 0;
        $absTicks = -1 === $this->sign ? -$ticks : $ticks;
        $this->seconds = intdiv($absTicks, self::MICRO_TO_SECONDS);
        $this->microseconds = $absTicks % self::MICRO_TO_SECONDS;
    }

    /**
     * @return array{0: array{total_microseconds: int}, 1:array{}}
     */
    public function __serialize(): array
    {
        return [['total_microseconds' => $this->ticks], []];
    }

    /**
     * @param array{0: array{total_microseconds: int}, 1:array{}} $data
     *
     * @throws TokeiException
     */
    public function __unserialize(array $data): void
    {
        [$properties] = $data;
        $new = new self($properties['total_microseconds']);
        $this->ticks = $new->ticks;
        $this->sign = $new->sign;
        $this->seconds = $new->seconds;
        $this->microseconds = $new->microseconds;
    }

    /**
     * @throws TokeiException
     */
    public static function of(
        int $weeks = 0,
        int $days = 0,
        int $hours = 0,
        int $minutes = 0,
        int $seconds = 0,
        int $milliseconds = 0,
        int $microseconds = 0,
    ): self {
        (0 <= $weeks && 0 <= $days && 0 <= $hours && 0 <= $minutes && 0 <= $seconds && 0 <= $milliseconds && 0 <= $microseconds) || throw new InvalidDuration('No duration part can be expressed with a negative number.');

        return self::fromParts([
            'weeks' => $weeks,
            'days' => $days,
            'hours' => $hours,
            'minutes' => $minutes,
            'seconds' => $seconds,
            'microseconds' => UnitTransformer::addTicks(UnitTransformer::toTicks($milliseconds, Unit::Millisecond), $microseconds),
            'sign' => '+',
        ]);
    }

    /**
     * Returns a new instance from a DateInterval object.
     *
     * if the DateInterval days property is false
     * and one of the y or m properties is set
     * an exception will be thrown as the object
     * will contain non-deterministic values
     *
     * @throws TokeiException
     */
    public static function fromDateInterval(DateInterval $interval): self
    {
        false !== $interval->days || (0 === $interval->y && 0 === $interval->m) || throw new InvalidDuration('fromDateInterval() does not handle non deterministic DateInterval properties like months and years.');
        (0.0 <= $interval->f && 1.0 > $interval->f) || throw new InvalidDuration('Invalid fractional seconds in DateInterval.');

        $days = false === $interval->days ? $interval->d : $interval->days;

        return self::fromParts([
            'days' => $days,
            'hours' => $interval->h,
            'minutes' => $interval->i,
            'seconds' => $interval->s,
            'microseconds' => UnitTransformer::toTicks($interval->f, Unit::Second),
            'sign' => 1 === $interval->invert ? '-' : '+',
        ]);
    }

    /**
     * @param TimeDuration $duration
     *
     * Because Duration does not handle nanoseconds
     * The \Time\Duration nanoseconds is trucated to the microxeconds
     *
     * @throws TokeiException
     */
    public static function fromNative(TimeDuration $duration): self
    {
        $instance = self::of(seconds: $duration->seconds, microseconds: intdiv($duration->nanoseconds, 1_000));

        return $duration->negative ? $instance->negate() : $instance;
    }

    /**
     * @throws TokeiException
     */
    public static function fromFormat(string $notation, DurationFormat $format): self
    {
        return match ($format) {
            DurationFormat::Iso8601 => self::fromIso8601($notation),
            DurationFormat::Timer => self::fromTimer($notation),
            DurationFormat::Compact => self::fromCompact($notation),
        };
    }

    /**
     * Creates a new instance from a timer string representation.
     *
     * @throws TokeiException
     */
    private static function fromTimer(string $notation): Duration
    {
        1 === preg_match(self::REGEXP_TIMER, $notation, $parts) || throw new InvalidDuration('Unknown or bad format `'.$notation.'`.');

        $hours = (int) $parts['hours'];
        $minutes = (int) $parts['minutes'];
        $seconds = (int) $parts['seconds'];
        $microseconds = intdiv((int) (str_pad($parts['fractions'] ?? '0', 9, '0')), 1000);

        ($minutes >= 0 && $minutes < 60) || throw InvalidDuration::dueToMalformedTime($minutes, Unit::Minute);
        ($seconds >= 0 && $seconds < 60) || throw InvalidDuration::dueToMalformedTime($seconds, Unit::Second);
        ($microseconds >= 0 && $microseconds < 1_000_000) || throw InvalidDuration::dueToMalformedTime($microseconds, Unit::Microsecond);

        return self::fromParts([
            'hours' => $hours,
            'minutes' => $minutes,
            'seconds' => $seconds,
            'microseconds' => $microseconds,
            'sign' => '-' === $parts['sign'] ? '-' : '+',
        ]);
    }

    /**
     * Creates a new instance from a timer string representation.
     *
     * Fractional values are allowed but with only one unit per notation.
     * 3m3ms is allowed
     * 3m3ms3µs is disallowed
     *
     * The fractions supported can be expressed in:
     *
     * - milliseconds
     * - microseconds
     *
     * @throws TokeiException
     */
    private static function fromCompact(string $notation): Duration
    {
        ('' !== $notation && 1 === preg_match(self::REGEXP_COMPACT, $notation, $parts)) || throw new InvalidDuration('Unknown or bad format `'.$notation.'`.');

        $parts['microseconds'] = self::resolveMicroseconds($parts);

        return self::fromParts($parts);
    }

    /**
     * Parses and returns a new instance from ISO8601 string representation.
     *
     * Because the duration does not handle in a deterministic way month and year components
     * the following restrictions apply:
     *
     * - only W, D, H, S are allowed
     * - Y is rejected
     * - M is only allowed in the time section (PT30M) to represents minutes
     * - fractional values are only allowed on seconds
     * - at least one unit must exist
     * - negative marker is allowed in front of the expression
     *
     * @throws TokeiException
     */
    private static function fromIso8601(string $notation): Duration
    {
        ('' !== $notation && 1 === preg_match(self::REGEXP_ISO8601, $notation, $parts)) || throw InvalidDuration::dueToMalformedIso8601($notation);

        $parts['fractions'] = intdiv((int) (str_pad($parts['fractions'] ?? '0', 9, '0')), 1000);
        $parts['microseconds'] = self::resolveMicroseconds($parts);

        return self::fromParts($parts);
    }

    /**
     * @param array{fractions?: InputDurationPart, unit?: ?non-empty-string} $parts
     *
     * @throws InvalidDuration
     */
    private static function resolveMicroseconds(array $parts): int
    {
        $value = (int) ($parts['fractions'] ?? 0);
        $unit = $parts['unit'] ?? 'us';
        if ('ms' === $unit) {
            ($value <= 999 && $value >= 0) || throw new InvalidDuration('millisecond fraction value cannot be greater than 999.');
            return $value * 1000;
        }

        if ('µs' === $unit || 'us' === $unit) {
            ($value <= 999_999 && $value >= 0) || throw new InvalidDuration('microsecond fraction value cannot be greater than 999_999.');

            return $value;
        }

        ($value <= 999_999_999 && $value >= 0) || throw new InvalidDuration('nanosecond fraction value cannot be greater than 999_999_999.');

        return intdiv($value, 1000);
    }

    /**
     * @param array{
     *     weeks? : InputDurationPart,
     *     days? : InputDurationPart,
     *     hours? : InputDurationPart,
     *     minutes? : InputDurationPart,
     *     seconds? : InputDurationPart,
     *     microseconds? : InputDurationPart,
     *     sign? : InputSign,
     * } $parts
     *
     * @throws TokeiException
     */
    private static function fromParts(array $parts): Duration
    {
        $sum = 0;
        $sum = UnitTransformer::addTicks($sum, UnitTransformer::toTicks((int) ($parts['weeks'] ?? 0), Unit::Week));
        $sum = UnitTransformer::addTicks($sum, UnitTransformer::toTicks((int) ($parts['days'] ?? 0), Unit::Day));
        $sum = UnitTransformer::addTicks($sum, UnitTransformer::toTicks((int) ($parts['hours'] ?? 0), Unit::Hour));
        $sum = UnitTransformer::addTicks($sum, UnitTransformer::toTicks((int) ($parts['minutes'] ?? 0), Unit::Minute));
        $sum = UnitTransformer::addTicks($sum, UnitTransformer::toTicks((int) ($parts['seconds'] ?? 0), Unit::Second));
        $sum = UnitTransformer::addTicks($sum, (int) ($parts['microseconds'] ?? 0));

        return new self('-' === ($parts['sign'] ?? '') ? -$sum : $sum);
    }

    /**
     * Returns an instance with 0s duration.
     */
    public static function zero(): self
    {
        return new self(0);
    }

    /**
     * Returns the duration of a complete 24-hour day.
     */
    public static function fullDay(): self
    {
        return self::of(days: 1);
    }

    /**
     * Returns an instance with the highest duration value supported by the package.
     */
    public static function max(): self
    {
        return new self(PHP_INT_MAX - 1);
    }

    /**
     * Returns an instance with the lowest duration value supported by the package.
     */
    public static function min(): self
    {
        return new self(PHP_INT_MIN + 1);
    }

    /**
     * Returns the shortest instance from a collection of instances.
     */
    public static function minOf(Duration|DateInterval|Interval|Task|TimeDuration ...$durations): self
    {
        [] !== $durations || throw new ArgumentCountError('minOf() expects at least one duration.');

        $durations = array_map(InputNormalizer::duration(...), $durations);
        usort($durations, Duration::compare(...));

        return $durations[array_key_first($durations)];
    }

    /**
     * Returns the longest instance from a collection of instances.
     */
    public static function maxOf(Duration|DateInterval|Interval|Task|TimeDuration ...$durations): self
    {
        [] !== $durations || throw new ArgumentCountError('maxOf() expects at least one duration.');

        $durations = array_map(InputNormalizer::duration(...), $durations);
        usort($durations, Duration::compare(...));

        return $durations[array_key_last($durations)];
    }

    /**
     * Encodes a Duration into a specified string notation representation.
     *
     * @return non-empty-string
     */
    public function format(DurationFormat $format): string
    {
        return $this->parts()->toDurationString($format);
    }

    /**
     * Converts the instance to an DateInterval object.
     */
    public function toDateInterval(?DateTimeInterface $relativeTo = null): DateInterval
    {
        return $this->parts()->toDateInterval($relativeTo);
    }

    private function parts(): DurationParts
    {
        return $this->parts ??= new DurationParts($this);
    }

    /**
     * Converts the instance to a \lib\Time\Duration object.
     */
    public function toNative(): TimeDuration
    {
        $new = TimeDuration::fromSeconds($this->seconds, $this->microseconds * 1_000);

        return -1 === $this->sign ? $new->negate() : $new;
    }

    /**
     * Returns the time as the number of unit of time since midnight.
     */
    public function in(Unit $unit): int|float
    {
        return UnitTransformer::fromTicks($this->ticks, $unit);
    }

    /**
     * Returns the duration expressed in the given unit as a numeric string.
     *
     * The precision controls the number of fractional digits in the resulting
     * string if present. If you need to round or truncate the duration itself,
     * use {@see Duration::roundTo()} before calling this method.
     *
     * @throws ValueError If $precision is negative.
     */
    public function toNumberString(Unit $unit, int $precision = 0): string
    {
        0 <= $precision || throw new ValueError('The precision cannot be negative.');

        $value = $this->in($unit);

        return is_int($value)
            ? (string) $value
            : number_format(num: $value, decimals: $precision, decimal_separator: '.', thousands_separator: '');
    }

    /**
     * @see self::format()
     *
     * @return non-empty-string
     */
    public function jsonSerialize(): string
    {
        return $this->format(DurationFormat::Iso8601);
    }

    /**
     * Returns true when the duration is zero, false otherwise.
     */
    public function isZero(): bool
    {
        return 0 === $this->ticks;
    }

    /**
     * Invert the duration sign.
     *
     * @throws TokeiException
     */
    public function negate(): self
    {
        return new self(-$this->ticks);
    }

    /**
     * @throws TokeiException
     */
    public function absolute(): self
    {
        return 0 > $this->ticks ? $this->negate() : $this;
    }

    /**
     * Returns a new instance rounded to the specified unit using a rounding mode.
     */
    public function roundTo(Unit $unit, SnapMode $mode = SnapMode::Nearest): self
    {
        $rounded = UnitTransformer::round($this->ticks, $unit, $mode);

        return $this->ticks === $rounded ? $this : new self($rounded);
    }

    /**
     * @throws TokeiException
     */
    public function add(Duration|DateInterval|Interval|Task|TimeDuration ...$other): self
    {
        $ticks = $this->ticks;
        foreach ($other as $item) {
            $ticks = self::addTicks($ticks, InputNormalizer::duration($item)->ticks);
        }

        return $ticks === $this->ticks ? $this : new self($ticks);
    }

    /**
     * @throws TokeiException
     */
    public function sub(Duration|DateInterval|Interval|Task|TimeDuration ...$other): self
    {
        $ticks = $this->ticks;
        foreach ($other as $item) {
            $ticks = self::addTicks($ticks, -InputNormalizer::duration($item)->ticks);
        }

        return $ticks === $this->ticks ? $this : new self($ticks);
    }

    /**
     * @throws TokeiException
     */
    private static function addTicks(int $left, int $right): int
    {
        return (($right > 0 && $left > PHP_INT_MAX - $right) || ($right < 0 && $left < PHP_INT_MIN - $right))
            ? throw InvalidDuration::dueToOverflow()
            : $left + $right;
    }

    /**
     * Compare this instance with another.
     *
     * @throws TokeiException
     *
     * @return int<-1, 1> If this duration is shorter, equal, or longer than the given duration.
     */
    public static function compare(
        Duration|DateInterval|Interval|Task|Time|Event|TimeDuration $that,
        Duration|DateInterval|Interval|Task|Time|Event|TimeDuration $other
    ): int {
        return InputNormalizer::duration($that)->ticks <=> InputNormalizer::duration($other)->ticks;
    }

    public function isLongerThan(Duration|DateInterval|Interval|Task|TimeDuration $other): bool
    {
        return 0 < self::compare($this, $other);
    }

    public function isLongerThanOrEqual(Duration|DateInterval|Interval|Task|TimeDuration $other): bool
    {
        return 0 <= self::compare($this, $other);
    }

    /**
     * Tells whether this instance is equal to the specified duration.
     */
    public function equals(Duration|DateInterval|Interval|Task|TimeDuration $other): bool
    {
        return 0 === self::compare($this, $other);
    }

    public function isShorterThanOrEqual(Duration|DateInterval|Interval|Task|TimeDuration $other): bool
    {
        return 0 >= self::compare($this, $other);
    }

    public function isShorterThan(Duration|DateInterval|Interval|Task|TimeDuration $other): bool
    {
        return 0 > self::compare($this, $other);
    }

    /**
     * Checks if this instance is within a certain bound.
     *
     * If the value is in range it returns the value, if the value is not in range it returns the nearest bound.
     *
     * @throws InvalidDuration
     */
    public function clamp(
        Duration|DateInterval|Interval|Task|TimeDuration $min,
        Duration|DateInterval|Interval|Task|TimeDuration $max
    ): self {
        $max = InputNormalizer::duration($max);
        $min = InputNormalizer::duration($min);

        $max->isLongerThanOrEqual($min) || throw new InvalidDuration('The maximum duration must be longer or equal to the minimum duration.');

        return match (true) {
            $this->isShorterThan($min) => $min,
            $this->isLongerThan($max) => $max,
            default => $this,
        };
    }

    /**
     * @throws TokeiException
     */
    public function multiplyBy(int $factor): self
    {
        $value = $this->ticks;
        $absFactor = abs($factor);

        return match (true) {
            -1 === $factor => $this->negate(),
            0 === $factor => self::zero(),
            1 === $factor,
            0 === $value => $this,
            ($value <= intdiv(PHP_INT_MAX, $absFactor) && $value >= intdiv(-PHP_INT_MAX, $absFactor)) => new self($value * $factor),
            default => throw InvalidDuration::dueToOverflow(),
        };
    }

    /**
     * Divides the duration by a factor using truncating integer division.
     *
     * The result is rounded toward zero.
     *
     * @throws TokeiException if the factor is zero
     */
    public function divideBy(int $factor): self
    {
        0 !== $factor || throw new DivisionByZeroError('Cannot divide by zero duration.');

        return new self(intdiv($this->ticks, $factor));
    }

    /**
     * Returns the number of Duration that can fit into the instance and the optional Duration remainder.
     *
     * @throws TokeiException
     */
    public function divideInto(Duration|DateInterval|Interval|Task|TimeDuration $duration): DivisionResult
    {
        $duration = InputNormalizer::duration($duration);

        return !$duration->isZero()
            ? new DivisionResult(
                quotient: intdiv($this->ticks, $duration->ticks),
                remainder: new self($this->ticks % $duration->ticks),
            )
            : throw new DivisionByZeroError('Cannot divide by zero duration.');
    }

    /**
     * Returns this duration modulo the given cycle.
     *
     * The result is always non-negative and strictly less than the cycle.
     *
     * @throws TokeiException
     */
    public function modulo(Duration|DateInterval|Interval|Task|TimeDuration $cycle): self
    {
        $cycle = InputNormalizer::duration($cycle);

        return new self(($this->ticks % $cycle->ticks + $cycle->ticks) % $cycle->ticks);
    }
}
