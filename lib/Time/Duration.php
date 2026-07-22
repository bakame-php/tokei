<?php

declare(strict_types=1);

namespace Time;

use ValueError;

use function class_exists;
use function intdiv;
use function preg_match;
use function str_pad;

use const PHP_INT_MAX;
use const PHP_VERSION_ID;
use const STR_PAD_RIGHT;

if (PHP_VERSION_ID < 80600 && !class_exists('Time\Duration')) {
    final readonly class Duration
    {
        private const int NANOS_PER_SECOND = 1_000_000_000;

        /**
         * Maximum seconds accepted by Duration.
         *
         * One second is reserved to avoid overflowing when arithmetic operations
         * temporarily require carrying nanoseconds into the seconds component.
         */
        private const int MAX_SECONDS = 9_223_372_035;

        /**
         * Regular expression to parse iso-8601 duration string.
         *
         * Year, Month, Week and Day components are intentionally not supported.
         * The biggest supported component is Hour.
         */
        private const string REGEXP_ISO8601 = '@^
            (?<sign>[+-])?
            PT
            (?=(?:\d+H|\d+M|\d+(?:[\.,]\d{1,9})?S)) # look ahead to avoid PT without argument
            (?:(?<hours>\d+)H)?
            (?:(?<minutes>\d+)M)?
            (?:
                (?<seconds>\d+)
                (?:[\.,](?<nanoseconds>\d{1,9}))
            ?S)?
        $@x';

        /**
         * @throws TimeException
         */
        private function __construct(
            public int $seconds,
            public int $nanoseconds,
            public bool $negative,
        ) {
            $seconds >= 0 || throw new ValueError('$seconds must be a non negative integer.');
            $nanoseconds >= 0 || throw new ValueError('$nanoseconds must be a non negative integer.');
            $seconds <= self::MAX_SECONDS || throw new TimeException('$seconds must be between 0 and '.self::MAX_SECONDS.' seconds (roughly 292 years)');
            $nanoseconds < self::NANOS_PER_SECOND || throw new TimeException('$nanoseconds must be between 0 and 999_999_999 nanoseconds.');
        }

        /**
         * Create a duration representing $seconds seconds and $nanoseconds nanoseconds. Neither parameter
         * may be negative. $nanoseconds must be less than 1_000_000_000 (the number of nanoseconds in a
         * second).
         *
         * This constructor creates a Duration from its “atomic” components.
         */
        public static function fromSeconds(int $seconds, int $nanoseconds = 0): self
        {
            return new self($seconds, $nanoseconds, false);
        }

        /**
         * Create a duration representing $nanoseconds nano-seconds. $nanoseconds must not be negative.
         *
         * @param non-negative-int $nanoseconds
         */
        public static function fromNanoseconds(int $nanoseconds): self
        {
            $seconds = intdiv($nanoseconds, self::NANOS_PER_SECOND);
            $nanoseconds = $nanoseconds % self::NANOS_PER_SECOND;

            return self::fromSeconds($seconds, $nanoseconds);
        }

        /**
         * Create a duration representing $microseconds micro-seconds.
         *
         * @param non-negative-int $microseconds
         *
         * @throws TimeException
         */
        public static function fromMicroseconds(int $microseconds): self
        {
            return self::fromNanoseconds(self::convertTo($microseconds, 1_000, 'microseconds', 'nanoseconds'));
        }

        /**
         * @param positive-int $factor
         * @param non-empty-string $fromUnit
         * @param non-empty-string $toUnit
         *
         * @throws TimeException
         *
         * @return non-negative-int
         */
        private static function convertTo(int $value, int $factor, string $fromUnit, string $toUnit): int
        {
            $value >= 0 || throw new ValueError("$value must be a non negative integer.");

            intdiv(PHP_INT_MAX, $factor) >= $value || throw new TimeException("Cannot convert $value $fromUnit to $toUnit: the resulting value exceeds the supported duration range.");

            return $value * $factor;
        }

        /**
         * Create a duration representing $milliseconds milliseconds.
         *
         * @param non-negative-int $milliseconds
         *
         * @throws TimeException
         */
        public static function fromMilliseconds(int $milliseconds): self
        {
            return self::fromNanoseconds(self::convertTo($milliseconds, 1_000_000, 'milliseconds', 'nanoseconds'));
        }

        /**
         * Create a duration representing $minutes minutes.
         *
         * @param non-negative-int $minutes
         *
         * @throws TimeException
         */
        public static function fromMinutes(int $minutes): self
        {
            return new self(self::convertTo($minutes, 60, 'minutes', 'seconds'), 0, false);
        }

        /**
         * Create a duration representing $hours hours.
         *
         * @param non-negative-int $hours
         *
         * @throws TimeException
         */
        public static function fromHours(int $hours): self
        {
            return new self(self::convertTo($hours, 3_600, 'hours', 'seconds'), 0, false);
        }

        /**
         * Parse a ISO-8601 period. ISO-8601 periods with a date component will be rejected.
         * The biggest allowed component is H.
         */
        public static function fromIso8601String(string $specification): self
        {
            1 === preg_match(self::REGEXP_ISO8601, $specification, $parts) || throw new TimeException("The submitted duration `$specification` is invalid or contains unsupported ISO 8601 duration components.");

            $hours = self::convertTo((int) ($parts['hours'] ?? 0), 3_600, 'hours', 'seconds');
            $minutes = self::convertTo((int)($parts['minutes'] ?? 0), 60, 'minutes', 'seconds');
            $seconds = (int) ($parts['seconds'] ?? 0);
            $nanoseconds = (int) (str_pad($parts['nanoseconds'] ?? '0', 9, '0', STR_PAD_RIGHT));

            (PHP_INT_MAX - $hours - $minutes >= $seconds) || throw new TimeException('Can not convert `'.$specification.'` specification; the resulting value exceeds the supported duration range.');

            $totalSeconds = $hours + $minutes + $seconds;

            return new self(
                $totalSeconds,
                $nanoseconds,
                negative: '-' === ($parts['sign'] ?? '') && (0 !== $totalSeconds || 0 !== $nanoseconds),
            );
        }

        /**
         * Negates the duration.
         *
         * @return self -$this
         */
        public function negate(): self
        {
            return 0 === $this->seconds && 0 === $this->nanoseconds
                ? $this
                : new self($this->seconds, $this->nanoseconds, !$this->negative);
        }

        /**
         * Add the given duration to the duration.
         *
         * @return self $this + $duration
         */
        public function add(self $duration): self
        {
            /* (+x) + (-y) == (+x) - (+y) */
            if (!$this->negative && $duration->negative) {
                return $this->sub($duration->negate());
            }
            /* (-x) + (+y) == (+y) - (+x) */
            if ($this->negative && !$duration->negative) {
                return $duration->sub($this->negate());
            }
            /* (-x) + (-y) = -((+x) + (+y))  */
            if ($this->negative) {
                return $this->negate()->add($duration->negate())->negate();
            }

            $seconds = $this->seconds + $duration->seconds;
            $nanoseconds = $this->nanoseconds + $duration->nanoseconds;

            $seconds += intdiv($nanoseconds, self::NANOS_PER_SECOND);
            $nanoseconds = $nanoseconds % self::NANOS_PER_SECOND;

            return self::fromSeconds($seconds, $nanoseconds);
        }

        /**
         * Subtract the given duration from the duration.
         *
         * @return self $this - $duration
         */
        public function sub(self $duration): self
        {
            /* (+x) - (-y) == (+x) + (+y) */
            if (!$this->negative && $duration->negative) {
                return $this->add($duration->negate());
            }
            /* (-x) - (+y) == -((+x) + (+y)) */
            if ($this->negative && !$duration->negative) {
                return $this->negate()->add($duration)->negate();
            }
            /* (-x) - (-y) == (-x) + (+y) */
            if ($this->negative) {
                return $this->add($duration->negate());
            }

            if (self::compare($this, $duration) < 0) {
                /* (+x) - (+y) = -((+y) - (+x)) */
                return $duration->sub($this)->negate();
            }

            $seconds = $this->seconds - $duration->seconds;
            $nanoseconds = $this->nanoseconds - $duration->nanoseconds;
            if ($nanoseconds < 0) {
                $nanoseconds += self::NANOS_PER_SECOND;
                $seconds--;
            }

            return self::fromSeconds($seconds, $nanoseconds);
        }

        /**
         * Multiply the length of the duration by the given factor. $factor must not be negative.
         *
         * @return self $this * $factor
         */
        public function multiplyBy(int $factor): self
        {
            if ($factor < 0) {
                throw new ValueError('$factor must not be negative');
            }

            $seconds = $this->seconds * $factor;
            $nanoseconds = $this->nanoseconds * $factor;

            $seconds += intdiv($nanoseconds, self::NANOS_PER_SECOND);
            $nanoseconds = $nanoseconds % self::NANOS_PER_SECOND;

            return new self($seconds, $nanoseconds, negative: $this->negative);
        }

        /**
         * Divide the length of the duration by the given divisor. $divisor must be positive.
         *
         * Fractional nanoseconds will be truncated.
         *
         * @return self $this / $factor
         */
        public function divideBy(int $divisor): self
        {
            if ($divisor <= 0) {
                throw new ValueError('$divisor must be positive');
            }

            $seconds = intdiv($this->seconds, $divisor);
            $nanoseconds = $this->nanoseconds + (($this->seconds % $divisor) * self::NANOS_PER_SECOND);
            $nanoseconds = intdiv($nanoseconds, $divisor);

            return new self($seconds, $nanoseconds, negative: $this->negative);
        }

        /**
         * Returns -1, 0, 1 if $a is less than, equal to, or greater than $b respectively.
         */
        public static function compare(self $a, self $b): int
        {
            if ($a->negative !== $b->negative) {
                return $a->negative ? -1 : 1;
            }

            $comparison = $a->seconds <=> $b->seconds;
            if (0 === $comparison) {
                $comparison = $a->nanoseconds <=> $b->nanoseconds;
            }

            return $a->negative ? -$comparison : $comparison;
        }
    }
}
