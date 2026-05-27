<?php

declare(strict_types=1);

namespace Bakame\Tokei;

use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use JsonSerializable;

use function abs;
use function array_column;
use function array_shift;
use function array_sum;
use function implode;
use function intdiv;
use function is_int;
use function preg_match;
use function round;
use function rtrim;
use function str_pad;

use const PHP_INT_MAX;
use const PHP_INT_MIN;
use const STR_PAD_LEFT;

final readonly class Duration implements JsonSerializable
{
    private const string DURATION_PATTERN = '/^
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
    $/x';

    public int $hours;
    public int $minutes;
    public int $seconds;
    public int $microseconds;
    public int $sign;
    public int $daysCount;
    public int $weeksCount;

    /**
     * @param int $value expressed in microseconds
     *
     * @throws InvalidDuration
     */
    private function __construct(private int $value)
    {
        ($value > PHP_INT_MIN + 1 && $value < PHP_INT_MAX) || throw InvalidDuration::dueToOverflow();

        $this->sign = $this->value <=> 0 ;
        $microseconds = abs($this->value);
        $this->weeksCount = Unit::Week->whole($microseconds);
        $this->daysCount = Unit::Day->whole($microseconds);
        $this->hours = Unit::Hour->whole($microseconds);
        $microseconds = Unit::Hour->remainder($microseconds);
        $this->minutes = Unit::Minute->whole($microseconds);
        $microseconds = Unit::Minute->remainder($microseconds);
        $this->seconds = Unit::Second->whole($microseconds);
        $this->microseconds = Unit::Second->remainder($microseconds);
    }

    /**
     * @throws InvalidDuration if the value can not be inverted
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
        return new self(self::toMicroseconds(
            days: ($weeks * 7) + $days,
            hours: $hours,
            minutes: $minutes,
            seconds: $seconds,
            microseconds: ($milliseconds * 1_000) + $microseconds
        ));
    }

    private static function toMicroseconds(
        int $days,
        int $hours,
        int $minutes,
        int|float $seconds,
        int $microseconds
    ): int {

        return Unit::Day->toMicroseconds($days)
            + Unit::Hour->toMicroseconds($hours)
            + Unit::Minute->toMicroseconds($minutes)
            + Unit::Second->toMicroseconds($seconds)
            + $microseconds;
    }

    /**
     * @throws InvalidDuration
     */
    public static function fromDateInterval(DateInterval $interval): self
    {
        (0 === $interval->y && 0 === $interval->m) || throw new InvalidDuration('fromDateInterval() does not handle non deterministic DateInterval properties like months and years.');
        (0.0 <= $interval->f && 1.0 >= $interval->f) || throw new InvalidDuration('Invalid fractional seconds in DateInterval.');

        $microseconds = self::toMicroseconds(
            days: false === $interval->days ? $interval->d : $interval->days,
            hours: $interval->h,
            minutes: $interval->i,
            seconds: $interval->s,
            microseconds: (int) round($interval->f * 1_000_000)
        );

        return new self(1 === $interval->invert ? -$microseconds : $microseconds);
    }

    /**
     * Parses and returns a new instance from ISO8601 string representation.
     *
     * @see self::parseIso8601()
     *
     * @throws InvalidDuration
     */
    public static function fromIso8601(string $notation): self
    {
        return self::parseIso8601($notation) ?? throw InvalidDuration::dueToMalformedIso8601($notation);
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
    public static function parseIso8601(string $notation): ?self
    {
        if (1 !== preg_match(self::DURATION_PATTERN, $notation, $parts)) {
            return null;
        }

        $microseconds = self::toMicroseconds(
            days: (((int) ($parts['weeks'] ?? 0) * 7) + (int) ($parts['days'] ?? 0)),
            hours: (int) ($parts['hours'] ?? 0),
            minutes: (int) ($parts['minutes'] ?? 0),
            seconds: (float) ($parts['seconds'] ?? 0),
            microseconds: 0
        );

        return self::of(microseconds: '-' === ($parts['sign'] ?? '-') ? -$microseconds : $microseconds);
    }

    /**
     * @throws InvalidDuration
     */
    public static function zero(): self
    {
        return new self(0);
    }

    /**
     * @throws InvalidDuration
     */
    public static function max(): self
    {
        return new self(PHP_INT_MAX - 1);
    }

    /**
     * @throws InvalidDuration
     */
    public static function min(): self
    {
        return new self(PHP_INT_MIN + 2);
    }

    /**
     * @throws InvalidDuration
     */
    public static function minOf(self ...$times): self
    {
        [] !== $times || throw new InvalidDuration('minOf() expects at least one duration');

        $min = array_shift($times);
        foreach ($times as $time) {
            if ($time->isShorterThan($min)) {
                $min = $time;
            }
        }

        return $min;
    }

    /**
     * @throws InvalidDuration
     */
    public static function maxOf(self ...$times): self
    {
        [] !== $times || throw new InvalidDuration('maxOf() expects at least one duration');

        $max = array_shift($times);
        foreach ($times as $time) {
            if ($time->isLongerThan($max)) {
                $max = $time;
            }
        }

        return $max;
    }

    /**
     * Returns the string representation of the Duration.
     *
     * The following format is used [-]HH:MM:SS[.mmmmmm]
     * the fraction and the signed are only display if
     * they duration is negative and/or the sub seconds
     * fraction is different from 0
     */
    public function toClockFormat(SubSecondDisplay $subSecondDisplay = SubSecondDisplay::Auto): string
    {
        $pad = static fn (int $value, int $length): string => str_pad((string) $value, $length, '0', STR_PAD_LEFT);
        $formatted = $this->hours.':'.$pad($this->minutes, 2).':'.$pad($this->seconds, 2);
        $includeSubSeconds = match ($subSecondDisplay) {
            SubSecondDisplay::Always => true,
            SubSecondDisplay::Never => false,
            SubSecondDisplay::Auto => 0 !== $this->microseconds,
        };

        if ($includeSubSeconds) {
            $formatted .= '.'.$pad($this->microseconds, 6);
        }

        return -1 === $this->sign ? '-'.$formatted : $formatted;
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
    public function toIso8601(): string
    {
        $time = '';
        $hours = $this->hours % 24;
        if (0 !== $hours) {
            $time .= $hours.'H';
        }

        if (0 !== $this->minutes) {
            $time .= $this->minutes.'M';
        }

        $seconds = (string) $this->seconds;
        if (0 !== $this->microseconds) {
            $seconds .= '.'.rtrim(
                    str_pad((string) $this->microseconds, 6, '0', STR_PAD_LEFT),
                    '0'
                );
        }
        if ('0' !== $seconds) {
            $time .= $seconds.'S';
        }

        return  (0 === $this->daysCount && '' === $time)
            ? 'PT0S'
            : (-1 === $this->sign ? '-' : '').'P'.(0 !== $this->daysCount ? $this->daysCount.'D' : '').('' !== $time ? 'T'.$time : '');
    }

    public function toCompact(): string
    {
        $time = [];
        if (0 !== $this->weeksCount) {
            $time[] = $this->weeksCount.'w';
        }

        $days = $this->daysCount % 7;
        if (0 !== $days) {
            $time[] = $days.'d';
        }

        $hours = $this->hours % 24;
        if (0 !== $hours) {
            $time[] = $hours.'h';
        }

        if (0 !== $this->minutes) {
            $time[] = $this->minutes.'m';
        }

        if (0 !== $this->seconds) {
            $time[] = $this->seconds.'s';
        }

        if (0 !== $this->microseconds) {
            $time[] = $this->microseconds.'µs';
        }

        return  [] === $time ? '0µs' : (-1 === $this->sign ? '-' : '').implode(' ', $time);
    }

    public function toDateInterval(?DateTimeInterface $relativeTo = null): DateInterval
    {
        $interval = new DateInterval('PT0S');
        $interval->d = $this->daysCount;
        $interval->h = $this->hours % 24;
        $interval->i = $this->minutes;
        $interval->s = $this->seconds;
        if (0 !== $this->microseconds) {
            $interval->f = Unit::Second->divide($this->microseconds);
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

    public function total(Unit $unit): int|float
    {
        return $unit->divide($this->value);
    }

    /**
     * @return non-empty-string
     */
    public function jsonSerialize(): string
    {
        return $this->toIso8601();
    }

    /**
     * Returns true when the duration is zero, false otherwise.
     */
    public function isEmpty(): bool
    {
        return 0 === $this->value;
    }

    /**
     * @throws InvalidDuration
     */
    public function negated(): self
    {
        return new self(-$this->value);
    }

    /**
     * @throws InvalidDuration
     */
    public function abs(): self
    {
        return $this->value < 0 ? $this->negated() : $this;
    }

    /**
     * @throws InvalidDuration
     */
    public function truncateTo(Unit $precision): self
    {
        $micro = abs($this->value);
        $truncated = $precision->truncate($micro);

        return $micro === $truncated ? $this : new self($this->sign * $truncated);
    }

    /**
     * @throws InvalidDuration
     */
    public function roundTo(Unit $precision): self
    {
        $micro = abs($this->value);
        $rounded = $precision->round($micro);

        return $micro === $rounded ? $this : new self($this->sign * $rounded);
    }

    /**
     * @throws InvalidDuration
     */
    public function sum(self ...$other): self
    {
        $other[] = $this;
        $value = array_sum(array_column($other, 'value'));
        is_int($value) || throw InvalidDuration::dueToOverflow(); /* @phpstan-ignore-line */

        return $this->value === $value ? $this : new self($value);
    }

    /**
     * @throws InvalidDuration if the value can not be inverted
     */
    public function increment(
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
     * @return int<-1, 1>
     */
    public function compareTo(self $other): int
    {
        return $this->value <=> $other->value;
    }

    public function equals(self $other): bool
    {
        return 0 === $this->compareTo($other);
    }

    public function isLongerThan(self $other): bool
    {
        return 0 < $this->compareTo($other);
    }

    public function isLongerThanOrEqual(self $other): bool
    {
        return 0 <= $this->compareTo($other);
    }
    public function isShorterThan(self $other): bool
    {
        return 0 > $this->compareTo($other);
    }

    public function isShorterThanOrEqual(self $other): bool
    {
        return 0 >= $this->compareTo($other);
    }

    /**
     * @throws InvalidDuration
     */
    public function clamp(self $min, self $max): self
    {
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
        $result = $this->value * $factor;

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
        0 !== $factor || throw new InvalidDuration('Unable to divide by zero.');

        return new self(intdiv($this->value, $factor));
    }

    /**
     * @return array{0: array{microseconds: int}, 1:array{}}
     */
    public function __serialize(): array
    {
        /** @var int $value */
        $value = $this->total(Unit::Microsecond);

        return [['microseconds' => $value], []];
    }

    /**
     * @param array{0: array{microseconds: int}, 1:array{}} $data
     *
     * @throws InvalidDuration
     */
    public function __unserialize(array $data): void
    {
        [$properties] = $data;
        $time = new self($properties['microseconds']);
        $this->value = $time->value;
        $this->hours = $time->hours;
        $this->minutes = $time->minutes;
        $this->seconds = $time->seconds;
        $this->microseconds = $time->microseconds;
        $this->daysCount = $time->daysCount;
        $this->weeksCount = $time->weeksCount;
        $this->sign = $time->sign;
    }
}
