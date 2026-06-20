<?php

declare(strict_types=1);

namespace Bakame\Tokei;

use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use JsonSerializable;
use ValueError;

use function array_column;
use function array_map;
use function array_sum;
use function implode;
use function intdiv;
use function is_int;
use function preg_match;
use function rtrim;
use function str_pad;
use function trim;

use const PHP_INT_MAX;
use const PHP_INT_MIN;
use const STR_PAD_LEFT;

final readonly class Duration implements JsonSerializable
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

    public int $microseconds;
    public int $sign;

    /**
     * @param int $microseconds expressed in microseconds
     *
     * @throws InvalidDuration
     */
    private function __construct(int $microseconds)
    {
        ($microseconds > PHP_INT_MIN + 1 && $microseconds < PHP_INT_MAX) || throw InvalidDuration::dueToOverflow();

        $this->microseconds = $microseconds;
        $this->sign = $this->microseconds <=> 0;
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
     * @throws InvalidDuration
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

        return new self(UnitTransformer::compose(
            days: ($weeks * 7) + $days,
            hours: $hours,
            minutes: $minutes,
            seconds: $seconds,
            microseconds: UnitTransformer::toMicroseconds($milliseconds, Unit::Millisecond) + $microseconds,
            sign: 1
        ));
    }

    /**
     * Returns a new instance from a DateInterval object.
     *
     * if the DateInterval days property is false
     * and one of the y or m properties is set
     * an exception will be thrown as the object
     * will contain non-deterministic values
     *
     * @throws InvalidDuration
     */
    public static function fromDateInterval(DateInterval $interval): self
    {
        false !== $interval->days || (0 === $interval->y && 0 === $interval->m) || throw new InvalidDuration('fromDateInterval() does not handle non deterministic DateInterval properties like months and years.');
        (0.0 <= $interval->f && 1.0 > $interval->f) || throw new InvalidDuration('Invalid fractional seconds in DateInterval.');

        return new self(UnitTransformer::compose(
            days: false === $interval->days ? $interval->d : $interval->days,
            hours: $interval->h,
            minutes: $interval->i,
            seconds: $interval->s,
            microseconds: UnitTransformer::toMicroseconds($interval->f, Unit::Second),
            sign: 1 === $interval->invert ? -1 : 1
        ));
    }

    /**
     * @throws InvalidDuration
     */
    public static function fromFormat(string $value, DurationFormat $format = DurationFormat::Iso8601): self
    {
        return match ($format) {
            DurationFormat::Iso8601 => self::fromIso8601($value),
            DurationFormat::Timer => self::fromTimer($value),
            DurationFormat::Compact => self::fromCompact($value),
        };
    }

    /**
     * Creates a new instance from a timer string representation.
     *
     * @throws InvalidDuration
     */
    private static function fromTimer(string $duration): Duration
    {
        1 === preg_match(self::REGEXP_TIMER, $duration, $parts) || throw new InvalidDuration('Unknown or bad format `'.$duration.'`.');

        $minutes = (int) $parts['minutes'];
        $seconds = (int) $parts['seconds'];
        $microseconds = (int) ($parts['microseconds'] ?? '0');

        ($minutes >= 0 && $minutes < 60) || throw InvalidDuration::dueToMalformedMinute($minutes);
        ($seconds >= 0 && $seconds < 60) || throw InvalidDuration::dueToMalformedSecond($seconds);
        ($microseconds >= 0 && $microseconds < 1_000_000) || throw InvalidDuration::dueToMalformedMicrosecond($microseconds);

        /** @var non-negative-int $microseconds */
        $microseconds = UnitTransformer::compose(
            days: 0,
            hours: (int) $parts['hours'],
            minutes: $minutes,
            seconds: $seconds,
            microseconds: $microseconds,
            sign: 1
        );

        $duration = self::of(microseconds: $microseconds);

        return '-' === $parts['sign'] ? $duration->negated() : $duration;
    }

    /**
     * Creates a new instance from a timer string representation.
     *
     * @throws InvalidDuration
     */
    private static function fromCompact(string $data): Duration
    {
        $data = trim($data);

        ('' !== $data && 1 === preg_match(self::REGEXP_COMPACT, $data, $parts)) || throw new InvalidDuration('Unknown or bad format `'.$data.'`.');

        /** @var non-negative-int $microseconds */
        $microseconds = UnitTransformer::compose(
            days: (((int) ($parts['weeks'] ?? 0) * 7) + (int) ($parts['days'] ?? 0)),
            hours: (int) ($parts['hours'] ?? 0),
            minutes: (int) ($parts['minutes'] ?? 0),
            seconds: (int) ($parts['seconds'] ?? 0),
            microseconds: (int) ($parts['microseconds'] ?? 0),
            sign: 1
        );

        $duration = self::of(microseconds: $microseconds);

        return '-' === ($parts['sign'] ?? '') ? $duration->negated() : $duration;
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
    private static function fromIso8601(string $data): Duration
    {
        1 === preg_match(self::REGEXP_ISO8601, $data, $parts) || throw InvalidDuration::dueToMalformedIso8601($data);

        /** @var non-negative-int $microseconds */
        $microseconds = UnitTransformer::compose(
            days: (((int) ($parts['weeks'] ?? 0) * 7) + (int) ($parts['days'] ?? 0)),
            hours: (int) ($parts['hours'] ?? 0),
            minutes: (int) ($parts['minutes'] ?? 0),
            seconds: (float) ($parts['seconds'] ?? 0),
            microseconds: 0,
            sign: 1,
        );

        $duration = self::of(microseconds: $microseconds);

        return '-' === ($parts['sign'] ?? '') ? $duration->negated() : $duration;
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
     *
     * @throws ValueError if no argument is given
     */
    public static function minOf(self ...$durations): self
    {
        $value = null;
        foreach ($durations as $duration) {
            if (null === $value || $duration->isShorterThan($value)) {
                $value = $duration;
            }
        }

        return null !== $value ? $value : throw new ValueError('minOf() expects at least one duration');
    }

    /**
     * Returns the longest instance from a collection of instances.
     *
     * @throws ValueError if no argument is given
     */
    public static function maxOf(self ...$durations): self
    {
        $value = null;
        foreach ($durations as $duration) {
            if (null === $value || $duration->isLongerThan($value)) {
                $value = $duration;
            }
        }

        return null !== $value ? $value : throw new ValueError('maxOf() expects at least one duration');
    }

    /**
     *  Compare this instance with another.
     *
     * @return int<-1, 1> If this duration is shorter, equal, or longer than the given duration.
     */
    public static function compare(
        Duration|DateInterval|Interval|Task|NativeInterval|NativeTask $that,
        Duration|DateInterval|Interval|Task|NativeInterval|NativeTask $other
    ): int {
        return InputNormalizer::duration($that)->microseconds <=> InputNormalizer::duration($other)->microseconds;
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
        $parsed = UnitTransformer::decompose($this->microseconds);
        $pad = static fn (int $value, int $length): string => str_pad((string) $value, $length, '0', STR_PAD_LEFT);

        $formatted = $pad($parsed->hours, 2).':'.$pad($parsed->minutes, 2).':'.$pad($parsed->seconds, 2);
        if (0 !== $parsed->microseconds) {
            $formatted .= '.'.$pad($parsed->microseconds, 6);
        }

        return -1 === $parsed->sign ? '-'.$formatted : $formatted;
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
        $parsed = UnitTransformer::decompose($this->microseconds);
        $time = '';
        if (0 < $parsed->hours || 0 < $parsed->minutes || 0 < $parsed->seconds || 0 < $parsed->microseconds) {
            $time = 'T';
            if (0 < $parsed->hours) {
                $time .= $parsed->hours.'H';
            }

            if (0 < $parsed->minutes) {
                $time .= $parsed->minutes.'M';
            }

            if (0 < $parsed->seconds || 0 < $parsed->microseconds) {
                $time .= $parsed->seconds;
                if (0 !== $parsed->microseconds) {
                    $time .= '.'.rtrim(str_pad((string) $parsed->microseconds, 6, '0', STR_PAD_LEFT), '0');
                }

                $time .= 'S';
            }
        }

        return '' === $time
            ? 'PT0S'
            : (-1 === $parsed->sign ? '-' : '').'P'.$time;
    }

    /**
     * Format [-]xw xd xh xm xs xµs where x is a number.
     * @return non-empty-string
     */
    private function toCompact(): string
    {
        $parsed = UnitTransformer::decompose($this->microseconds);
        $time = [];
        if (0 !== $parsed->weeksCount) {
            $time[] = $parsed->weeksCount.'w';
        }

        $days = $parsed->daysCount % 7;
        if (0 !== $days) {
            $time[] = $days.'d';
        }

        $hours = $parsed->hours % 24;
        if (0 !== $hours) {
            $time[] = $hours.'h';
        }

        if (0 !== $parsed->minutes) {
            $time[] = $parsed->minutes.'m';
        }

        if (0 !== $parsed->seconds) {
            $time[] = $parsed->seconds.'s';
        }

        if (0 !== $parsed->microseconds) {
            $time[] = $parsed->microseconds.'µs';
        }

        return [] === $time ? '0s' : (-1 === $parsed->sign ? '-' : '').implode('', $time);
    }


    /**
     * Converts the instance to an DateInterval object.
     */
    public function toDateInterval(?DateTimeInterface $relativeTo = null): DateInterval
    {
        $parsed = UnitTransformer::decompose($this->microseconds);
        $interval = new DateInterval('PT0S');
        $interval->d = $parsed->daysCount;
        $interval->h = $parsed->hours % 24;
        $interval->i = $parsed->minutes;
        $interval->s = $parsed->seconds;
        if (0 !== $parsed->microseconds) {
            $interval->f = UnitTransformer::fromMicroseconds($parsed->microseconds, Unit::Second);
        }
        $interval->invert = -1 === $parsed->sign ? 1 : 0;
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
     * @throws InvalidDuration
     */
    public function negated(): self
    {
        return new self(-$this->microseconds);
    }

    /**
     * @throws InvalidDuration
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
     * @throws InvalidDuration
     */
    public function sum(Duration|DateInterval|Interval|Task|NativeInterval|NativeTask ...$other): self
    {
        $other = array_map(InputNormalizer::duration(...), $other);
        $other[] = $this;
        $microseconds = array_sum(array_column($other, 'microseconds'));
        is_int($microseconds) || throw InvalidDuration::dueToOverflow(); /* @phpstan-ignore-line */

        return $this->microseconds === $microseconds ? $this : new self($microseconds);
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
     * @throws InvalidDuration
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
     * @throws InvalidDuration
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
     * Tells whether this instance is equal to the specified duration.
     */
    public function equals(Duration|DateInterval|Interval|Task|NativeInterval|NativeTask $other): bool
    {
        return 0 === self::compare($this, $other);
    }

    public function isLongerThan(Duration|DateInterval|Interval|Task|NativeInterval|NativeTask $other): bool
    {
        return 0 < self::compare($this, $other);
    }

    public function isLongerThanOrEqual(Duration|DateInterval|Interval|Task|NativeInterval|NativeTask $other): bool
    {
        return 0 <= self::compare($this, $other);
    }
    public function isShorterThan(Duration|DateInterval|Interval|Task|NativeInterval|NativeTask $other): bool
    {
        return 0 > self::compare($this, $other);
    }

    public function isShorterThanOrEqual(Duration|DateInterval|Interval|Task|NativeInterval|NativeTask $other): bool
    {
        return 0 >= self::compare($this, $other);
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
     * @throws InvalidDuration if value overflow
     */
    public function multipliedBy(int $factor): self
    {
        $result = $this->microseconds * $factor;

        is_int($result) || throw InvalidDuration::dueToOverflow(); /* @phpstan-ignore-line */

        return new self($result);
    }

    /**
     * Divides the duration by a factor using truncating integer division.
     *
     * The result is rounded toward zero.
     *
     * @throws InvalidDuration if the factor is zero
     */
    public function dividedBy(int $factor): self
    {
        0 !== $factor || throw new InvalidDuration('factor must be a positive integer.');

        return new self(intdiv($this->microseconds, $factor));
    }

    public function countOf(Duration|DateInterval|Interval|Task|NativeInterval|NativeTask $other): int
    {
        $other = InputNormalizer::duration($other);

        return !$other->isZero()
            ? intdiv($this->microseconds, $other->microseconds)
            : throw new InvalidDuration('Cannot divide by zero duration.');
    }

    public function remainder(Duration|DateInterval|Interval|Task|NativeInterval|NativeTask $other): self
    {
        $other = InputNormalizer::duration($other);

        return !$other->isZero()
            ? new self($this->microseconds % $other->microseconds)
            : throw new InvalidDuration('Cannot divide by zero duration.');
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
     * @throws InvalidDuration
     */
    public function __unserialize(array $data): void
    {
        [$properties] = $data;
        $duration = new self($properties['microseconds']);
        $this->microseconds = $duration->microseconds;
        $this->sign = $duration->sign;
    }
}
