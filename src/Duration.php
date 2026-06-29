<?php

declare(strict_types=1);

namespace Bakame\Tokei;

use ArgumentCountError;
use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use DivisionByZeroError;
use JsonSerializable;

use function array_key_first;
use function array_key_last;
use function implode;
use function intdiv;
use function preg_match;
use function rtrim;
use function str_pad;
use function usort;

use const PHP_INT_MAX;
use const PHP_INT_MIN;
use const STR_PAD_LEFT;

final class Duration implements JsonSerializable
{
    private const string REGEXP_TIMER = '@^
        (?<sign>-)?\s*
        (?<hours>\d+):
        (?<minutes>\d{1,2}):
        (?<seconds>\d{1,2})
        (\.(?<microseconds>\d+))?
    $@x';

    private const string REGEXP_COMPACT = '@^
        (?<sign>-)?\s*
        (?:(?<weeks>\d+)\s*w\s*)?
        (?:(?<days>\d+)\s*d\s*)?
        (?:(?<hours>\d+)\s*h\s*)?
        (?:(?<minutes>\d+)\s*m\s*)?
        (?:(?<seconds>\d+)\s*s\s*)?
        (?:(?<microseconds>\d+)\s*(µs|us)\s*)?
    $@x';

    private const string REGEXP_ISO8601 = '@^
        (?<sign>[+-])?
        P
        (?=.*?(?:\d+W|\d+D|T\d+H|T\d+M|T\d+(?:\.\d+)?S)) # look-ahead to restrict support for ISO8601 formats
        (?:(?<weeks>\d+)W)?
        (?:(?<days>\d+)D)?
        (?:T
            (?:(?<hours>\d+)H)?
            (?:(?<minutes>\d+)M)?
            (?:(?<seconds>\d+(?:\.\d+)?)S)?
        )?
    $@x';

    /** Total duration expressed in the library base unit. */
    public readonly int $microseconds;
    public readonly int $sign;
    private DurationParts $parts;

    /**
     * @param int $microseconds expressed in microseconds
     *
     * @throws TokeiException
     */
    private function __construct(int $microseconds)
    {
        PHP_INT_MIN !== $microseconds || throw InvalidDuration::dueToOverflow();

        $this->microseconds = $microseconds;
        $this->sign = $this->microseconds <=> 0;
        $this->parts = DurationParts::parse($this->microseconds);
    }

    /**
     * @param non-negative-int $weeks
     * @param non-negative-int $days
     * @param non-negative-int $hours
     * @param non-negative-int $minutes
     * @param non-negative-int $seconds
     * @param non-negative-int $milliseconds
     * @param non-negative-int $microseconds
     *
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
        /* @phpstan-ignore-next-line */
        (0 <= $weeks && 0 <= $days && 0 <= $hours && 0 <= $minutes && 0 <= $seconds && 0 <= $milliseconds && 0 <= $microseconds) || throw new InvalidDuration('No duration part can be expressed with a negative number.');

        return new self(
            new DurationParts(
                hours: (($weeks * 7) + $days) * 24 + $hours,
                minutes: $minutes,
                seconds: $seconds,
                microseconds: UnitTransformer::toMicroseconds($milliseconds, Unit::Millisecond) + $microseconds,
                sign: 1,
            )->build()
        );
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

        return new self(
            new DurationParts(
                hours: ($days) * 24 + $interval->h,
                minutes: $interval->i,
                seconds: $interval->s,
                microseconds: UnitTransformer::toMicroseconds($interval->f, Unit::Second),
                sign: 1 === $interval->invert ? -1 : 1,
            )->build()
        );
    }

    /**
     * @throws TokeiException
     */
    public static function fromFormat(string $notation, DurationFormat $format = DurationFormat::Iso8601): self
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

        $minutes = (int) $parts['minutes'];
        $seconds = (int) $parts['seconds'];
        $microseconds = (int) ($parts['microseconds'] ?? '0');

        ($minutes >= 0 && $minutes < 60) || throw InvalidDuration::dueToMalformedMinute($minutes);
        ($seconds >= 0 && $seconds < 60) || throw InvalidDuration::dueToMalformedSecond($seconds);
        ($microseconds >= 0 && $microseconds < 1_000_000) || throw InvalidDuration::dueToMalformedMicrosecond($microseconds);

        return new self(
            new DurationParts(
                hours: (int)$parts['hours'],
                minutes: $minutes,
                seconds: $seconds,
                microseconds: $microseconds,
                sign: '-' === $parts['sign'] ? -1 : 1,
            )->build()
        );
    }

    /**
     * Creates a new instance from a timer string representation.
     *
     * @throws TokeiException
     */
    private static function fromCompact(string $notation): Duration
    {
        ('' !== $notation && 1 === preg_match(self::REGEXP_COMPACT, $notation, $parts)) || throw new InvalidDuration('Unknown or bad format `'.$notation.'`.');

        return new self(
            new DurationParts(
                hours: ((((int)($parts['weeks'] ?? 0) * 7) + (int)($parts['days'] ?? 0)) * 24) + (int)($parts['hours'] ?? 0),
                minutes: (int)($parts['minutes'] ?? 0),
                seconds: (int)($parts['seconds'] ?? 0),
                microseconds: (int)($parts['microseconds'] ?? 0),
                sign: '-' === ($parts['sign'] ?? '') ? -1 : 1,
            )->build()
        );
    }

    /**
     * Parses and returns a new instance from ISO8601 string representation.
     *  Because the duration does not handle in a deterministic way month and year components
     * the following restrictions apply:
     *
     * - only W, D, H, S are allowed
     * - Y is rejected
     * - M is only allowed in the time section (PT30M) to represents minutes
     * - fractional values are only allowed on seconds
     * - at least one unit must exist
     * - negative marker is allowed in front of the expression
     *
     * @throws InvalidDuration
     */
    private static function fromIso8601(string $notation): Duration
    {
        1 === preg_match(self::REGEXP_ISO8601, $notation, $parts) || throw InvalidDuration::dueToMalformedIso8601($notation);

        return new self(
            new DurationParts(
                hours: (int)($parts['hours'] ?? 0) + ((((int)($parts['weeks'] ?? 0) * 7) + (int)($parts['days'] ?? 0)) * 24),
                minutes: (int)($parts['minutes'] ?? 0),
                seconds: 0,
                microseconds: UnitTransformer::toMicroseconds((float)($parts['seconds'] ?? 0), Unit::Second),
                sign: '-' === ($parts['sign'] ?? '') ? -1 : 1,
            )->build(),
        );
    }

    /**
     * Returns an instance with 0s duration.
     */
    public static function zero(): self
    {
        /** @var ?self $duration */
        static $duration = null;

        return $duration ??= new self(0);
    }

    /**
     * Returns an instance with the highest duration value supported by the package.
     */
    public static function max(): self
    {
        /** @var ?self $duration */
        static $duration = null;

        return $duration ??= new self(PHP_INT_MAX - 1);
    }

    /**
     * Returns an instance with the lowest duration value supported by the package.
     */
    public static function min(): self
    {
        /** @var ?self $duration */
        static $duration = null;

        return $duration ??= new self(PHP_INT_MIN + 2);
    }

    /**
     * Returns the shortest instance from a collection of instances.
     */
    public static function minOf(self ...$durations): self
    {
        [] !== $durations || throw new ArgumentCountError('minOf() expects at least one duration.');
        usort($durations, Duration::compare(...));

        return $durations[array_key_first($durations)];
    }

    /**
     * Returns the longest instance from a collection of instances.
     */
    public static function maxOf(self ...$durations): self
    {
        [] !== $durations || throw new ArgumentCountError('maxOf() expects at least one duration.');
        usort($durations, Duration::compare(...));

        return $durations[array_key_last($durations)];
    }

    /**
     * @return array{0: array{microseconds: int}, 1:array{}}
     */
    public function __serialize(): array
    {
        return [['microseconds' => $this->microseconds], []];
    }

    /**
     * @param array{0: array{microseconds: int}, 1: array{}} $data
     *
     * @throws TokeiException
     */
    public function __unserialize(array $data): void
    {
        [$properties] = $data;
        $duration = new self($properties['microseconds']);
        $this->microseconds = $duration->microseconds;
        $this->sign = $duration->sign;
        $this->parts = $duration->parts;
    }

    /**
     * Encodes a Duration into a specified string notation representation.
     *
     * @return non-empty-string
     */
    public function format(DurationFormat $format = DurationFormat::Iso8601): string
    {
        return match ($format) {
            DurationFormat::Iso8601 => $this->toIso8601(),
            DurationFormat::Timer => $this->toTimer(),
            DurationFormat::Compact => $this->toCompact(),
        };
    }

    /**
     * Returns the string representation of the Duration.
     *
     * The following format is used [-]HH:MM:SS[.mmmmmm]
     * the fraction and the signed are only display if
     * they duration is negative and/or the sub seconds
     * fraction is different from 0
     *
     * @return non-empty-string
     */
    private function toTimer(): string
    {
        $pad = static fn (int $value, int $length): string => str_pad((string) $value, $length, '0', STR_PAD_LEFT);
        $formatted = $pad($this->parts->hours, 2).':'.$pad($this->parts->minutes, 2).':'.$pad($this->parts->seconds, 2);
        if (0 !== $this->parts->microseconds) {
            $formatted .= '.'.$pad($this->parts->microseconds, 6);
        }

        return -1 === $this->parts->sign ? '-'.$formatted : $formatted;
    }

    /**
     * Returns the ISO8601 string representation of the duration.
     *
     * - fractional values are only allowed on seconds
     * - only D, H, M and S are allowed; M represents the minutes
     * - negative marker is allowed in front of the expression
     *
     * @return non-empty-string
     */
    private function toIso8601(): string
    {
        $time = '';
        if (0 < $this->parts->hours || 0 < $this->parts->minutes || 0 < $this->parts->seconds || 0 < $this->parts->microseconds) {
            $time = 'T';
            if (0 < $this->parts->hours) {
                $time .= $this->parts->hours.'H';
            }

            if (0 < $this->parts->minutes) {
                $time .= $this->parts->minutes.'M';
            }

            if (0 < $this->parts->seconds || 0 < $this->parts->microseconds) {
                $time .= $this->parts->seconds;
                if (0 !== $this->parts->microseconds) {
                    $time .= '.'.rtrim(str_pad((string) $this->parts->microseconds, 6, '0', STR_PAD_LEFT), '0');
                }

                $time .= 'S';
            }
        }

        return '' === $time
            ? 'PT0S'
            : (-1 === $this->parts->sign ? '-' : '').'P'.$time;
    }

    /**
     * Format [-]xw xd xh xm xs xµs where x is a number.
     * @return non-empty-string
     */
    private function toCompact(): string
    {
        $value = -1 === $this->sign ? -$this->microseconds : $this->microseconds;
        $time = [];
        $weeksCount = UnitTransformer::whole($value, Unit::Week);
        if (0 !== $weeksCount) {
            $time[] = $weeksCount.'w';
        }

        $days = UnitTransformer::whole($value, Unit::Day) % 7;
        if (0 !== $days) {
            $time[] = $days.'d';
        }

        $hours = $this->parts->hours % 24;
        if (0 !== $hours) {
            $time[] = $hours.'h';
        }

        if (0 !== $this->parts->minutes) {
            $time[] = $this->parts->minutes.'m';
        }

        if (0 !== $this->parts->seconds) {
            $time[] = $this->parts->seconds.'s';
        }

        if (0 !== $this->parts->microseconds) {
            $time[] = $this->parts->microseconds.'µs';
        }

        return [] === $time ? '0s' : (-1 === $this->sign ? '-' : '').implode('', $time);
    }

    /**
     * Converts the instance to an DateInterval object.
     */
    public function toDateInterval(?DateTimeInterface $relativeTo = null): DateInterval
    {
        $interval = new DateInterval('PT0S');
        $interval->d = UnitTransformer::whole($this->microseconds, Unit::Day);
        $interval->h = $this->parts->hours % 24;
        $interval->i = $this->parts->minutes;
        $interval->s = $this->parts->seconds;
        if (0 !== $this->parts->microseconds) {
            $interval->f = UnitTransformer::fromMicroseconds($this->parts->microseconds, Unit::Second);
        }
        $interval->invert = -1 === $this->sign ? 1 : 0;
        if (null === $relativeTo) {
            return $interval;
        }

        if (!$relativeTo instanceof DateTimeImmutable) {
            $relativeTo = DateTimeImmutable::createFromInterface($relativeTo);
        }

        return $relativeTo->diff($relativeTo->add($interval));
    }

    /**
     * Returns the Duration as expressed in the specified Unit of time.
     */
    public function in(Unit $unit): int|float
    {
        return UnitTransformer::fromMicroseconds($this->microseconds, $unit);
    }

    public function component(Unit $unit): int
    {
        $value = -1 === $this->sign ? -$this->microseconds : $this->microseconds;
        $whole = UnitTransformer::whole($value, $unit);

        return match ($unit) {
            Unit::Week => $whole,
            Unit::Day => $whole % 7,
            Unit::Hour => $whole % 24,
            Unit::Minute, Unit::Second => $whole % 60,
            default => $whole % 1_000,
        };
    }

    /**
     * @see self::format()
     *
     * @return non-empty-string
     */
    public function jsonSerialize(): string
    {
        return $this->format();
    }

    /**
     * Returns true when the duration is zero, false otherwise.
     */
    public function isZero(): bool
    {
        return 0 === $this->microseconds;
    }

    /**
     * Invert the duration sign.
     *
     * @throws TokeiException
     */
    public function negated(): self
    {
        return new self(-$this->microseconds);
    }

    /**
     * @throws TokeiException
     */
    public function abs(): self
    {
        return $this->microseconds < 0 ? $this->negated() : $this;
    }

    /**
     * Returns a new instance rounded to the specified unit using a rounding mode.
     */
    public function roundTo(Unit $unit, SnapMode $mode = SnapMode::Nearest): self
    {
        $rounded = UnitTransformer::round($this->microseconds, $unit, $mode);

        return $this->microseconds === $rounded ? $this : new self($rounded);
    }

    /**
     * @throws TokeiException
     */
    public function sum(Duration|DateInterval|Interval|Task|NativeInterval|NativeTask ...$other): self
    {
        $microseconds = $this->microseconds;
        foreach ($other as $item) {
            $value = InputNormalizer::duration($item)->microseconds;
            if (($value > 0 && $microseconds > PHP_INT_MAX - $value) || ($value < 0 && $microseconds < PHP_INT_MIN - $value)) {
                throw InvalidDuration::dueToOverflow();
            }

            $microseconds += $value;
        }

        return $microseconds === $this->microseconds ? $this : new self($microseconds);
    }

    /**
     * @param non-negative-int $weeks
     * @param non-negative-int $days
     * @param non-negative-int $hours
     * @param non-negative-int $minutes
     * @param non-negative-int $seconds
     * @param non-negative-int $milliseconds
     * @param non-negative-int $microseconds
     *
     * @throws TokeiException
     */
    public function increase(
        int $weeks = 0,
        int $days = 0,
        int $hours = 0,
        int $minutes = 0,
        int $seconds = 0,
        int $milliseconds = 0,
        int $microseconds = 0
    ): self {
        return $this->sum(self::of(
            weeks: $weeks,
            days: $days,
            hours: $hours,
            minutes: $minutes,
            seconds: $seconds,
            milliseconds: $milliseconds,
            microseconds: $microseconds
        ));
    }

    /**
     * @param non-negative-int $weeks
     * @param non-negative-int $days
     * @param non-negative-int $hours
     * @param non-negative-int $minutes
     * @param non-negative-int $seconds
     * @param non-negative-int $milliseconds
     * @param non-negative-int $microseconds
     *
     * @throws TokeiException
     */
    public function decrease(
        int $weeks = 0,
        int $days = 0,
        int $hours = 0,
        int $minutes = 0,
        int $seconds = 0,
        int $milliseconds = 0,
        int $microseconds = 0
    ): self {
        return $this->sum(self::of(
            weeks: $weeks,
            days: $days,
            hours: $hours,
            minutes: $minutes,
            seconds: $seconds,
            milliseconds: $milliseconds,
            microseconds: $microseconds
        )->negated());
    }

    /**
     * Compare this instance with another.
     *
     * @throws TokeiException
     *
     * @return int<-1, 1> If this duration is shorter, equal, or longer than the given duration.
     */
    public static function compare(
        Duration|DateInterval|Interval|Task|NativeInterval|NativeTask $that,
        Duration|DateInterval|Interval|Task|NativeInterval|NativeTask $other
    ): int {
        return InputNormalizer::duration($that)->microseconds <=> InputNormalizer::duration($other)->microseconds;
    }

    public function isLongerThan(Duration|DateInterval|Interval|Task|NativeInterval|NativeTask $other): bool
    {
        return 0 < self::compare($this, $other);
    }

    public function isLongerThanOrEqual(Duration|DateInterval|Interval|Task|NativeInterval|NativeTask $other): bool
    {
        return 0 <= self::compare($this, $other);
    }

    /**
     * Tells whether this instance is equal to the specified duration.
     */
    public function equals(Duration|DateInterval|Interval|Task|NativeInterval|NativeTask $other): bool
    {
        return 0 === self::compare($this, $other);
    }

    public function isShorterThanOrEqual(Duration|DateInterval|Interval|Task|NativeInterval|NativeTask $other): bool
    {
        return 0 >= self::compare($this, $other);
    }

    public function isShorterThan(Duration|DateInterval|Interval|Task|NativeInterval|NativeTask $other): bool
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
        Duration|DateInterval|Interval|Task|NativeInterval|NativeTask $min,
        Duration|DateInterval|Interval|Task|NativeInterval|NativeTask $max
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
    public function multipliedBy(int $factor): self
    {
        if (1 === $factor || 0 === $this->microseconds) {
            return $this;
        }

        if (-1 === $factor) {
            return $this->negated();
        }

        if (0 === $factor) {
            return self::zero();
        }

        $value = $this->microseconds;
        $absFactor = abs($factor);

        return ($value <= intdiv(PHP_INT_MAX, $absFactor) && $value >= intdiv(-PHP_INT_MAX, $absFactor))
            ? new self($value * $factor)
            : throw InvalidDuration::dueToOverflow();
    }

    /**
     * Divides the duration by a factor using truncating integer division.
     *
     * The result is rounded toward zero.
     *
     * @throws TokeiException if the factor is zero
     */
    public function dividedBy(int $factor): self
    {
        0 !== $factor || throw new DivisionByZeroError('Cannot divide by zero duration.');

        return new self(intdiv($this->microseconds, $factor));
    }

    /**
     * Returns the number of Duration that can fit into the instance and the optional Duration remainder.
     *
     * @throws TokeiException
     */
    public function dividedInto(Duration|DateInterval|Interval|Task|NativeInterval|NativeTask $duration): DivisionResult
    {
        $duration = InputNormalizer::duration($duration);

        !$duration->isZero() || throw new DivisionByZeroError('Cannot divide by zero duration.');

        return new DivisionResult(
            factor: intdiv($this->microseconds, $duration->microseconds),
            remainder: new self($this->microseconds % $duration->microseconds),
        );
    }
}
