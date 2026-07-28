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
 * @phpstan-type SerializedDuration array{0: array{seconds: non-negative-int, nanoseconds: non-negative-int, sign:-1|0|1}, 1: array{}}
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

    /** @var positive-int */
    private const int TICKS_PER_SECOND = 1_000_000_000;
    private ?DurationParts $parts = null;

    /**
     * @param non-negative-int $seconds
     * @param non-negative-int $nanoseconds
     * @param -1|0|1 $sign
     */
    private function __construct(
        public readonly int $seconds,
        public readonly int $nanoseconds,
        public readonly int $sign
    ) {
        PHP_INT_MAX >= (($this->seconds * self::TICKS_PER_SECOND) + $this->nanoseconds) || throw InvalidDuration::dueToOverflow();
    }

    private static function fromTicks(int $ticks): self
    {
        PHP_INT_MIN !== $ticks || throw InvalidDuration::dueToOverflow();

        $sign = $ticks <=> 0;
        $absTicks = abs($ticks);
        /** @var non-negative-int $seconds */
        $seconds = intdiv($absTicks, self::TICKS_PER_SECOND);
        $nanoseconds = $absTicks % self::TICKS_PER_SECOND;

        return new self($seconds, $nanoseconds, $sign);
    }

    /**
     * @return SerializedDuration
     */
    public function __serialize(): array
    {
        return [['seconds' => $this->seconds, 'nanoseconds' => $this->nanoseconds, 'sign' => $this->sign], []];
    }

    /**
     * @param SerializedDuration $data
     */
    public function __unserialize(array $data): void
    {
        [$properties] = $data;
        $this->sign = $properties['sign'];
        $this->seconds = $properties['seconds'];
        $this->nanoseconds = $properties['nanoseconds'];
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
        int $nanoseconds = 0,
    ): self {
        (0 <= $weeks && 0 <= $days && 0 <= $hours && 0 <= $minutes && 0 <= $seconds && 0 <= $milliseconds && 0 <= $microseconds && 0 <= $nanoseconds) || throw new InvalidDuration('No duration part can be expressed with a negative number.');

        $nanosecond = UnitTransformer::add(
            UnitTransformer::toTicks($milliseconds, Unit::Millisecond),
            UnitTransformer::toTicks($microseconds, Unit::Microsecond)
        );

        return self::fromParts([
            'weeks' => $weeks,
            'days' => $days,
            'hours' => $hours,
            'minutes' => $minutes,
            'seconds' => $seconds,
            'nanoseconds' => UnitTransformer::add($nanoseconds, $nanosecond),
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
            'nanoseconds' => UnitTransformer::toTicks($interval->f, Unit::Second),
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
        /* @phpstan-ignore-next-line */
        return new self($duration->seconds, $duration->nanoseconds, match (true) {
            $duration->negative => -1,
            0 === $duration->seconds && 0 === $duration->nanoseconds => 0,
            default => 1,
        });
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
        $nanoseconds = (int) (str_pad($parts['fractions'] ?? '0', 9, '0'));

        ($minutes >= 0 && $minutes < 60) || throw InvalidDuration::dueToMalformedTime($minutes, Unit::Minute);
        ($seconds >= 0 && $seconds < 60) || throw InvalidDuration::dueToMalformedTime($seconds, Unit::Second);
        ($nanoseconds >= 0 && $nanoseconds < self::TICKS_PER_SECOND) || throw InvalidDuration::dueToMalformedTime($nanoseconds, Unit::Microsecond);

        return self::fromParts([
            'hours' => $hours,
            'minutes' => $minutes,
            'seconds' => $seconds,
            'nanoseconds' => $nanoseconds,
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

        $parts['nanoseconds'] = self::resolveMicroseconds($parts);

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

        $parts['fractions'] = (int) (str_pad($parts['fractions'] ?? '0', 9, '0'));
        $parts['nanoseconds'] = self::resolveMicroseconds($parts);

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
        $value >= 0 || throw new InvalidDuration('the fraction cannot be negative.');
        $unit = $parts['unit'] ?? 'ns';
        if ('ms' === $unit) {
            $value <= 999 || throw new InvalidDuration('millisecond fraction value cannot be greater than 999.');

            return $value * 1_000_000;
        }

        if ('µs' === $unit || 'us' === $unit) {
            $value <= 999_999 || throw new InvalidDuration('microsecond fraction value cannot be greater than 999_999.');

            return $value * 1_000;
        }

        $value < self::TICKS_PER_SECOND || throw new InvalidDuration('nanosecond fraction value cannot be greater than 999_999_999.');

        return $value;
    }

    /**
     * @param array{
     *     weeks? : InputDurationPart,
     *     days? : InputDurationPart,
     *     hours? : InputDurationPart,
     *     minutes? : InputDurationPart,
     *     seconds? : InputDurationPart,
     *     nanoseconds? : InputDurationPart,
     *     sign? : InputSign,
     * } $parts
     *
     * @throws TokeiException
     */
    private static function fromParts(array $parts): Duration
    {
        $seconds = 0;
        $seconds = UnitTransformer::add($seconds, (int) UnitTransformer::convert((int) ($parts['weeks'] ?? 0), Unit::Week, Unit::Second));
        $seconds = UnitTransformer::add($seconds, (int) UnitTransformer::convert((int) ($parts['days'] ?? 0), Unit::Day, Unit::Second));
        $seconds = UnitTransformer::add($seconds, (int) UnitTransformer::convert((int) ($parts['hours'] ?? 0), Unit::Hour, Unit::Second));
        $seconds = UnitTransformer::add($seconds, (int) UnitTransformer::convert((int) ($parts['minutes'] ?? 0), Unit::Minute, Unit::Second));
        $seconds = UnitTransformer::add($seconds, (int) ($parts['seconds'] ?? 0));

        $new = self::fromComponents($seconds, UnitTransformer::add(0, (int) ($parts['nanoseconds'] ?? 0)));

        return ('-' === ($parts['sign'] ?? '')) ? $new->negate() : $new;
    }

    private static function fromComponents(int $seconds, int $nanoseconds): self
    {
        // Normalize carries first.
        if ($nanoseconds >= self::TICKS_PER_SECOND || $nanoseconds <= -self::TICKS_PER_SECOND) {
            $seconds += intdiv($nanoseconds, self::TICKS_PER_SECOND);
            $nanoseconds %= self::TICKS_PER_SECOND;
        }

        // Ensure seconds and nanoseconds have the same sign.
        if ($seconds > 0 && $nanoseconds < 0) {
            --$seconds;
            $nanoseconds += self::TICKS_PER_SECOND;
        } elseif ($seconds < 0 && $nanoseconds > 0) {
            ++$seconds;
            $nanoseconds -= self::TICKS_PER_SECOND;
        }

        if (0 === $seconds && 0 === $nanoseconds) {
            return self::zero();
        }

        $sign = $seconds < 0 || (0 === $seconds && $nanoseconds < 0) ? -1 : 1;

        return new self(abs($seconds), abs($nanoseconds), $sign);
    }

    /**
     * Returns an instance with 0s duration.
     */
    public static function zero(): self
    {
        return new self(0, 0, 0);
    }

    /**
     * Returns the duration of a complete 24-hour day.
     */
    public static function fullDay(): self
    {
        return new self(86_400, 0, 1);
    }

    /**
     * Returns an instance with the highest duration value supported by the package.
     */
    public static function max(): self
    {
        return self::fromTicks(PHP_INT_MAX);
    }

    /**
     * Returns an instance with the lowest duration value supported by the package.
     */
    public static function min(): self
    {
        return self::max()->negate();
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

    private function ticks(): int
    {
        return $this->sign * (UnitTransformer::toTicks($this->seconds, Unit::Second) + $this->nanoseconds);
    }

    /**
     * Converts the instance to a \Time\Duration object.
     */
    public function toNative(): TimeDuration
    {
        $new = TimeDuration::fromSeconds($this->seconds, $this->nanoseconds);

        return -1 === $this->sign ? $new->negate() : $new;
    }

    /**
     * Returns the time as the number of unit of time since midnight.
     */
    public function in(Unit $unit): int|float
    {
        return $this->parts()->toUnit($unit);
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
        return 0 === $this->sign;
    }

    /**
     * Invert the duration sign.
     *
     * @throws TokeiException
     */
    public function negate(): self
    {
        return 0 === $this->sign ? $this : new self($this->seconds, $this->nanoseconds, -$this->sign);
    }

    /**
     * @throws TokeiException
     */
    public function absolute(): self
    {
        return -1 === $this->sign ? $this->negate() : $this;
    }

    /**
     * Returns a new instance rounded to the specified unit using a rounding mode.
     */
    public function roundTo(Unit $unit, SnapMode $mode = SnapMode::Nearest): self
    {
        $ticks = $this->ticks();
        $rounded = UnitTransformer::round($ticks, $unit, $mode);

        return $ticks === $rounded ? $this : self::fromTicks($rounded);
    }

    /**
     * @throws TokeiException
     */
    public function sub(Duration|DateInterval|Interval|Task|TimeDuration ...$other): self
    {
        return $this->add(...array_map(
            static fn ($item): Duration => InputNormalizer::duration($item)->negate(),
            $other
        ));
    }

    /**
     * @throws TokeiException
     */
    public function add(Duration|DateInterval|Interval|Task|TimeDuration ...$other): self
    {
        $safeAdd = static fn (int $left, int $right): int => (($right > 0 && $left > PHP_INT_MAX - $right) || ($right < 0 && $left < PHP_INT_MIN - $right))
            ? throw InvalidDuration::dueToOverflow()
            : $left + $right;

        $seconds = $this->signedSeconds();
        $nanoseconds = $this->signedNanoseconds();
        foreach ($other as $item) {
            $item = InputNormalizer::duration($item);
            $seconds = $safeAdd($seconds, $item->signedSeconds());
            $nanoseconds += $item->signedNanoseconds();
        }

        $new = self::fromComponents($seconds, $nanoseconds);

        return $new->equals($this) ? $this : $new;
    }

    private function signedSeconds(): int
    {
        return $this->sign * $this->seconds;
    }

    private function signedNanoseconds(): int
    {
        return $this->sign * $this->nanoseconds;
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
        $that = InputNormalizer::duration($that);
        $other = InputNormalizer::duration($other);
        if ($that->sign !== $other->sign) {
            return $that->sign <=> $other->sign;
        }

        if (0 === $that->sign) {
            return 0;
        }

        $compareAbsolute = static fn (Duration $that, Duration $other): int
        => $that->seconds !== $other->seconds
            ? $that->seconds <=> $other->seconds
            : $that->nanoseconds <=> $other->nanoseconds;

        $comparison = $compareAbsolute($that, $other);

        return $that->sign < 0 ? -$comparison : $comparison;
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
        $mul = static function (Duration $duration, int $factor): self {
            $absFactor = abs($factor);

            ($duration->seconds <= intdiv(PHP_INT_MAX, $absFactor)) || throw InvalidDuration::dueToOverflow();
            ($duration->nanoseconds <= intdiv(PHP_INT_MAX, $absFactor)) || throw InvalidDuration::dueToOverflow();

            return self::fromComponents(
                $duration->signedSeconds() * $factor,
                $duration->signedNanoseconds() * $factor
            );
        };

        return match (true) {
            -1 === $factor => $this->negate(),
            0 === $factor => self::zero(),
            1 === $factor,
            0 === $this->sign => $this,
            default => $mul($this, $factor),
        };
    }

    /**
     * Divides the duration by a factor using truncating integer division.
     *
     * The result is rounded toward zero.
     *
     * @throws TokeiException if the factor is zero
     */
    public function divideBy(int $divisor): self
    {
        $div = static function (Duration $duration, int $divisor): self {
            $seconds = $duration->signedSeconds();

            return self::fromComponents(
                intdiv($seconds, $divisor),
                intdiv($duration->signedNanoseconds() + (($seconds % $divisor) * self::TICKS_PER_SECOND), $divisor)
            );
        };

        return match (true) {
            -1 === $divisor => $this->negate(),
            0 === $divisor => throw new DivisionByZeroError('Cannot divide by zero duration.'),
            1 === $divisor,
            0 === $this->sign => $this,
            default => $div($this, $divisor),
        };
    }

    /**
     * Returns the number of Duration that can fit into the instance and the optional Duration remainder.
     *
     * @throws TokeiException
     */
    public function divideInto(Duration|DateInterval|Interval|Task|TimeDuration $duration): DivisionResult
    {
        $div = static function (Duration $duration, Duration $divisor): DivisionResult {
            $thisTicks = $duration->ticks();
            $otherTicks = $divisor->ticks();

            return new DivisionResult(
                quotient: intdiv($thisTicks, $otherTicks),
                remainder: self::fromTicks($thisTicks % $otherTicks),
            );
        };

        $duration = InputNormalizer::duration($duration);

        return match (true) {
            $duration->isZero() => throw new DivisionByZeroError('Cannot divide by zero duration.'),
            $this->isZero() => new DivisionResult(quotient: 0, remainder: self::zero()),
            default => $div($this, $duration),
        };
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
        $cycleTicks = InputNormalizer::duration($cycle)->ticks();

        return self::fromTicks(($this->ticks() % $cycleTicks + $cycleTicks) % $cycleTicks);
    }
}
