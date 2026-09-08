<?php

declare(strict_types=1);

namespace Bakame\Tokei;

use ArgumentCountError;
use ArithmeticError;
use Bakame\Tokei\Internal\DurationComponents;
use Bakame\Tokei\Internal\DurationParts;
use Bakame\Tokei\Internal\InputNormalizer;
use Bakame\Tokei\Internal\Parser;
use Bakame\Tokei\Internal\UnitTransformer;
use DateInterval;
use DivisionByZeroError;
use JsonSerializable;
use Time\Duration as TimeDuration;
use ValueError;

use function abs;
use function array_key_first;
use function array_key_last;
use function array_map;
use function intdiv;
use function str_pad;
use function str_split;
use function usort;

use const PHP_INT_MAX;
use const PHP_INT_MIN;
use const PHP_INT_SIZE;
use const STR_PAD_LEFT;

/**
 * @phpstan-type SerializedDuration array{0: array{seconds: int, nanoseconds: int, sign: int}, 1: array{}}
 */
final readonly class Duration implements JsonSerializable
{
    /** @var positive-int */
    private const int TICKS_PER_SECOND = 1_000_000_000;
    private const int MAX_SECOND = PHP_INT_SIZE >= 8 ? 9_223_372_035 : PHP_INT_MAX;
    public bool $negative;

    /**
     * @throws InvalidDuration
     */
    private function __construct(
        public int $seconds,
        public int $nanoseconds,
        private int $sign
    ) {
        $seconds >= 0 || throw new InvalidDuration('$seconds must be a non negative integer.');
        ($nanoseconds >= 0 && $nanoseconds < self::TICKS_PER_SECOND) || throw new InvalidDuration('$nanoseconds must be between 0 and '.(self::TICKS_PER_SECOND - 1).'.');
        ($sign >= -1 && $sign <= 1) || throw new InvalidDuration('$sign must be -1, 0 or 1.');

        $isZero = 0 === $seconds && 0 === $nanoseconds;

        !$isZero || 0 === $sign || throw new InvalidDuration('A zero duration must have a sign of 0.');
        $isZero || 0 !== $sign || throw new InvalidDuration('A non-zero duration cannot have a sign of 0.');
        $seconds <= self::MAX_SECOND || throw InvalidDuration::dueToOverflow();
        $this->negative = -1 === $this->sign;
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
     *
     * @throws InvalidDuration
     */
    public function __unserialize(array $data): void
    {
        [$properties] = $data;
        $new = new self($properties['seconds'], $properties['nanoseconds'], $properties['sign']);
        $this->seconds = $new->seconds;
        $this->nanoseconds = $new->nanoseconds;
        $this->sign = $new->sign;
        $this->negative = $new->negative;
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
        return self::fromComponents(Parser::parseDateInterval($interval));
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
        return self::fromComponents(Parser::parseNativeDuration($duration));
    }

    /**
     * Parses a duration from the given string representation.
     *
     * @see DurationFormat
     *
     * @throws TokeiException
     */
    public static function fromFormat(string $notation, DurationFormat $format): self
    {
        return self::fromComponents(Parser::parseDurationNotation($notation, $format));
    }

    public static function fromWeeks(int $weeks): self
    {
        return self::of(weeks: $weeks);
    }

    public static function fromDays(int $days): self
    {
        return self::of(days: $days);
    }

    public static function fromHours(int $hours): self
    {
        return self::of(hours: $hours);
    }

    public static function fromMinutes(int $minutes): self
    {
        return self::of(minutes: $minutes);
    }

    public static function fromSeconds(int $seconds, int $nanoseconds = 0): self
    {
        return self::of(seconds: $seconds, nanoseconds: $nanoseconds);
    }

    public static function fromMilliseconds(int $milliseconds): self
    {
        return self::of(milliseconds: $milliseconds);
    }

    public static function fromMicroseconds(int $microseconds): self
    {
        return self::of(microseconds: $microseconds);
    }

    public static function fromNanoseconds(int $nanoseconds): self
    {
        return self::of(nanoseconds: $nanoseconds);
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

        $nano = 0;
        $nano = UnitTransformer::add($nano, UnitTransformer::toTicks($milliseconds, Unit::Millisecond));
        $nano = UnitTransformer::add($nano, UnitTransformer::toTicks($microseconds, Unit::Microsecond));
        $nano = UnitTransformer::add($nano, $nanoseconds);

        $sec = 0;
        $sec = UnitTransformer::add($sec, (int) UnitTransformer::convert($weeks, Unit::Week, Unit::Second));
        $sec = UnitTransformer::add($sec, (int) UnitTransformer::convert($days, Unit::Day, Unit::Second));
        $sec = UnitTransformer::add($sec, (int) UnitTransformer::convert($hours, Unit::Hour, Unit::Second));
        $sec = UnitTransformer::add($sec, (int) UnitTransformer::convert($minutes, Unit::Minute, Unit::Second));
        $sec = UnitTransformer::add($sec, $seconds);

        return self::create($sec, $nano);
    }

    /**
     * @throws InvalidDuration
     */
    private static function fromTicks(int $ticks): self
    {
        return self::create(intdiv($ticks, self::TICKS_PER_SECOND), $ticks % self::TICKS_PER_SECOND);
    }

    /**
     * @throws InvalidDuration
     */
    private static function fromComponents(DurationComponents $components): self
    {
        return self::create($components->seconds, $components->nanoseconds);
    }

    /**
     * @throws InvalidDuration
     */
    private static function create(int $seconds, int $nanoseconds): self
    {
        if ($nanoseconds >= self::TICKS_PER_SECOND || $nanoseconds <= -self::TICKS_PER_SECOND) {
            $seconds += intdiv($nanoseconds, self::TICKS_PER_SECOND);
            $nanoseconds %= self::TICKS_PER_SECOND;
        }

        if ($seconds > 0 && $nanoseconds < 0) {
            --$seconds;
            $nanoseconds += self::TICKS_PER_SECOND;
        }

        if ($seconds < 0 && $nanoseconds > 0) {
            ++$seconds;
            $nanoseconds -= self::TICKS_PER_SECOND;
        }

        return 0 === $seconds && 0 === $nanoseconds
            ? self::zero()
            : new self(
                seconds: abs($seconds),
                nanoseconds: abs($nanoseconds),
                sign: $seconds < 0 || (0 === $seconds && $nanoseconds < 0) ? -1 : 1
            );
    }

    /**
     * Returns an instance with 0s duration.
     */
    public static function zero(): self
    {
        return new self(seconds: 0, nanoseconds: 0, sign: 0);
    }

    /**
     * Returns the duration of a complete 24-hour day.
     */
    public static function fullDay(): self
    {
        return new self(seconds: 86_400, nanoseconds: 0, sign: 1);
    }

    /**
     * Returns an instance with the lowest duration value supported by the package.
     */
    public static function min(): self
    {
        return self::max()->negate();
    }

    /**
     * Returns an instance with the highest duration value supported by the package.
     */
    public static function max(): self
    {
        return new self(seconds: self::MAX_SECOND, nanoseconds: self::TICKS_PER_SECOND - 1, sign: 1);
    }

    /**
     * Returns the shortest instance from a collection of instances.
     */
    public static function minOf(Duration|DateInterval|Interval|Task|TimeDuration ...$durations): self
    {
        [] !== $durations || throw new ArgumentCountError(__METHOD__.'() expects at least one duration argument.');

        $durations = array_map(InputNormalizer::duration(...), $durations);
        usort($durations, Duration::compare(...));

        return $durations[array_key_first($durations)];
    }

    /**
     * Returns the longest instance from a collection of instances.
     */
    public static function maxOf(Duration|DateInterval|Interval|Task|TimeDuration ...$durations): self
    {
        [] !== $durations || throw new ArgumentCountError(__METHOD__.'() expects at least one duration argument.');

        $durations = array_map(InputNormalizer::duration(...), $durations);
        usort($durations, Duration::compare(...));

        return $durations[array_key_last($durations)];
    }

    /**
     * Compare this instance with another.
     *
     * @throws TokeiException
     *
     * @return int<-1, 1> If this duration is shorter, equal, or longer than the given duration.
     */
    public static function compare(
        Duration|DateInterval|Interval|Task|Time|Event|TimeDuration $a,
        Duration|DateInterval|Interval|Task|Time|Event|TimeDuration $b
    ): int {
        $a = InputNormalizer::duration($a);
        $b = InputNormalizer::duration($b);
        if ($a->sign !== $b->sign) {
            return $a->sign <=> $b->sign;
        }

        if (0 === $a->sign) {
            return 0;
        }

        $comparison = $a->seconds !== $b->seconds
            ? $a->seconds <=> $b->seconds
            : $a->nanoseconds <=> $b->nanoseconds;

        return $a->sign < 0 ? -$comparison : $comparison;
    }

    /**
     * Formats the duration according to the given representation.
     *
     * @see DurationFormat
     *
     * @throws TokeiException
     *
     * @return non-empty-string
     */
    public function format(DurationFormat $format): string
    {
        return new DurationParts($this)->toDurationString($format);
    }

    /**
     * Returns the signed duration expressed in the library's internal base unit.
     *
     * This is an internal implementation detail and should not be relied upon
     * outside arithmetic operations.
     */
    private function ticks(): int
    {
        return $this->sign * (UnitTransformer::toTicks($this->seconds, Unit::Second) + $this->nanoseconds);
    }

    /**
     * Returns the time as the number of unit of time since midnight.
     */
    public function in(Unit $unit): int|float
    {
        return new DurationParts($this)->toNumber($unit);
    }

    /**
     * Returns the duration expressed in the given unit as a numeric string.
     *
     * The precision controls the number of fractional digits in the resulting
     * string if present. If you need to round or truncate the duration itself,
     * use {@see Duration::roundTo()} before calling this method.
     *
     * @param non-negative-int $precision
     *
     * @throws ValueError If $precision is negative.
     *
     * @return non-empty-string
     */
    public function toNumberString(Unit $unit, int $precision = 0, DisplaySign $displaySign = DisplaySign::Auto): string
    {
        return new DurationParts($this)->toNumberString($unit, $precision, $displaySign);
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
     *
     * @throws TokeiException
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
        $result = $this;
        foreach ($other as $item) {
            $result = $result->addDuration($item);
        }

        return $result->equals($this) ? $this : $result;
    }

    /**
     * Add the given duration to the duration.
     *
     * @throws TokeiException
     */
    private function addDuration(Duration|DateInterval|Interval|Task|TimeDuration $duration): self
    {
        $duration = InputNormalizer::duration($duration);
        $seconds = $this->negative ? -$this->seconds : $this->seconds;
        $nanoseconds = $this->negative ? -$this->nanoseconds : $this->nanoseconds;
        $addSeconds = $duration->negative ? -$duration->seconds : $duration->seconds;
        $addNanoseconds = $duration->negative ? -$duration->nanoseconds : $duration->nanoseconds;

        if ($this->negative === $duration->negative && $this->seconds > self::MAX_SECOND - $duration->seconds) {
            throw InvalidDuration::dueToOverflow();
        }

        $seconds += $addSeconds;
        $nanoseconds += $addNanoseconds;

        if ($nanoseconds >= self::TICKS_PER_SECOND || $nanoseconds <= -self::TICKS_PER_SECOND) {
            $carry = intdiv($nanoseconds, self::TICKS_PER_SECOND);
            $nanoseconds %= self::TICKS_PER_SECOND;
            if (($carry > 0 && $seconds > self::MAX_SECOND - $carry) || ($carry < 0 && $seconds < -self::MAX_SECOND - $carry)) {
                throw InvalidDuration::dueToOverflow();
            }

            $seconds += $carry;
        }

        if (0 < $seconds && 0 > $nanoseconds) {
            --$seconds;
            $nanoseconds += self::TICKS_PER_SECOND;
        }

        if (0 > $seconds && 0 < $nanoseconds) {
            ++$seconds;
            $nanoseconds -= self::TICKS_PER_SECOND;
        }

        return self::create($seconds, $nanoseconds);
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
     * Multiplies the duration by the given factor.
     *
     * @throws TokeiException
     */
    public function multiplyBy(int $factor): self
    {
        if (0 === $factor || 0 === $this->sign) {
            return self::zero();
        }

        if (1 === $factor) {
            return $this;
        }

        if (-1 === $factor) {
            return $this->negate();
        }

        PHP_INT_MIN !== $factor || throw InvalidDuration::dueToOverflow();

        $sign = $this->sign * ($factor < 0 ? -1 : 1);
        $factor = abs($factor);

        $seconds = 0;
        $nanoseconds = 0;
        $addSeconds = $this->seconds;
        $addNanoseconds = $this->nanoseconds;

        while (true) {
            if (1 === ($factor & 1)) {
                [$seconds, $nanoseconds] = self::addMagnitudes($seconds, $nanoseconds, $addSeconds, $addNanoseconds);
            }

            $factor >>= 1;

            if (0 === $factor) {
                break;
            }

            [$addSeconds, $addNanoseconds] = self::addMagnitudes($addSeconds, $addNanoseconds, $addSeconds, $addNanoseconds);
        }

        return self::create($sign * $seconds, $sign * $nanoseconds);
    }

    /**
     * Adds two non-negative (seconds, nanoseconds) pairs.
     *
     * @throws TimeException when the sum is out of the representable range
     *
     * @return array{0: int, 1: int}
     */
    private static function addMagnitudes(int $seconds, int $nanoseconds, int $addSeconds, int $addNanoseconds): array
    {
        ($seconds <= self::MAX_SECOND - $addSeconds) || throw InvalidDuration::dueToOverflow();

        $seconds += $addSeconds;
        $nanoseconds += $addNanoseconds;

        if ($nanoseconds >= self::TICKS_PER_SECOND) {
            self::MAX_SECOND !== $seconds || throw InvalidDuration::dueToOverflow();

            ++$seconds;
            $nanoseconds -= self::TICKS_PER_SECOND;
        }

        return [$seconds, $nanoseconds];
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
        0 !== $divisor || throw new DivisionByZeroError('Cannot divide by zero.');
        if (1 === $divisor || 0 === $this->sign) {
            return $this;
        }

        PHP_INT_MIN !== $divisor || throw new ArithmeticError('Cannot divide by PHP_INT_MIN.');

        $sign = $this->sign;
        if ($divisor < 0) {
            $sign = -$sign;
            $divisor = -$divisor;
        }

        $remainder = $this->seconds % $divisor;
        $nanoseconds = $remainder > intdiv(PHP_INT_MAX - $this->nanoseconds, self::TICKS_PER_SECOND)
            ? self::divideNanoseconds($remainder, $this->nanoseconds, $divisor)
            : intdiv($this->nanoseconds + $remainder * self::TICKS_PER_SECOND, $divisor);

        return new self(intdiv($this->seconds, $divisor), $nanoseconds, $sign);
    }

    /**
     * Returns intdiv($remainder * 1_000_000_000 + $nanoseconds, $divisor) for
     * a $remainder that is lower than $divisor.
     *
     * The dividend does not fit in an integer on 32 bit platforms, so the
     * division is done one decimal digit at a time. Every intermediate value
     * stays below 2**53, where floats represent integers exactly.
     */
    private static function divideNanoseconds(int $remainder, int $nanoseconds, int $divisor): int
    {
        $quotient = 0;
        $rest = (float) $remainder;

        foreach (str_split(str_pad((string) $nanoseconds, 9, '0', STR_PAD_LEFT)) as $digit) {
            $rest = $rest * 10 + (int) $digit;
            $digit = (int) ($rest / $divisor);
            $rest -= $digit * $divisor;

            // the float division may be off by one in either direction
            while (0 > $rest) {
                --$digit;
                $rest += $divisor;
            }
            while ($rest >= $divisor) {
                ++$digit;
                $rest -= $divisor;
            }

            $quotient = $quotient * 10 + $digit;
        }

        return $quotient;
    }

    /**
     * Returns the number of times the given duration fits into this instance, together with the remainder.
     *
     * @throws TokeiException
     */
    public function divideInto(Duration|DateInterval|Interval|Task|TimeDuration $duration): DivisionResult
    {
        $duration = InputNormalizer::duration($duration);
        !$duration->isZero() || throw new DivisionByZeroError('Cannot divide by zero duration.');

        if ($this->isZero()) {
            return new DivisionResult(0, self::zero());
        }

        $thisTicks = $this->ticks();
        $otherTicks = $duration->ticks();

        return new DivisionResult(intdiv($thisTicks, $otherTicks), self::fromTicks($thisTicks % $otherTicks));
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

        0 !== $cycleTicks || throw new DivisionByZeroError('Cannot calculate modulo by zero duration.');

        return self::fromTicks(($this->ticks() % $cycleTicks + $cycleTicks) % $cycleTicks);
    }
}
