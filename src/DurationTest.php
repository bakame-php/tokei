<?php

declare(strict_types=1);

namespace Bakame\Tokei;

use DateInterval;
use DateTime;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

use function json_encode;
use function ltrim;
use function serialize;
use function substr;
use function unserialize;

use const PHP_INT_MAX;
use const PHP_INT_MIN;

#[CoversClass(InvalidDuration::class)]
#[CoversClass(Duration::class)]
#[CoversClass(DurationFormat::class)]
#[CoversClass(Unit::class)]
final class DurationTest extends TestCase
{
    public function testParseMicroseconds(): void
    {
        $duration = Duration::of(weeks:5, days:6, hours: 2, minutes: 15, seconds: 42, microseconds: 123_456);

        self::assertSame(5, $duration->weeksCount);
        self::assertSame(41, $duration->daysCount);
        self::assertSame(2 + (41 * 24), $duration->hours);
        self::assertSame(15, $duration->minutes);
        self::assertSame(42, $duration->seconds);
        self::assertSame(123_456, $duration->microseconds);
        self::assertSame(1, $duration->sign);
        self::assertSame(3_550_542_123_456, $duration->total(Unit::Microsecond));
        self::assertSame('5w 6d 2h 15m 42s 123456µs', $duration->format(DurationFormat::Compact));
    }

    public function testParseNegativeMicroseconds(): void
    {
        $duration = Duration::of(microseconds: -1_500_000);

        self::assertSame(0, $duration->hours);
        self::assertSame(0, $duration->minutes);
        self::assertSame(1, $duration->seconds);
        self::assertSame(500_000, $duration->microseconds);
        self::assertSame(-1, $duration->sign);
        self::assertSame('-1s 500000µs', $duration->format(DurationFormat::Compact));
    }

    public function testFormatMicrosecondsWithoutFraction(): void
    {
        self::assertSame('9:25:00', Duration::of(hours: 9, minutes: 25)->format(DurationFormat::Clock));
    }

    public function testFormatMicrosecondsWithFraction(): void
    {
        self::assertSame('1:02:03.000045', Duration::of(hours: 1, minutes: 2, seconds: 3, microseconds: 45)->format(DurationFormat::Clock));
    }

    public function testFormatNegativeMicroseconds(): void
    {
        self::assertSame('-4:05:06', Duration::of(hours: 4, minutes: 5, seconds: 6)->negated()->format(DurationFormat::Clock));
    }

    public function testMicrosecondsToDateInterval(): void
    {
        $interval = Duration::of(hours: 27, minutes: 12, seconds: 5, microseconds: 123_456)->toDateInterval();

        self::assertSame(1, $interval->d);
        self::assertSame(3, $interval->h);
        self::assertSame(12, $interval->i);
        self::assertSame(5, $interval->s);
        self::assertSame(0, $interval->invert);
        self::assertFalse($interval->days);

        self::assertEqualsWithDelta(0.123456, $interval->f, 0.000001);
    }

    public function testMicrosecondsToDateIntervalWithDateReference(): void
    {
        $interval = Duration::of(hours: 27, minutes: 12, seconds: 5, microseconds: 123_456)->toDateInterval(new DateTime());

        self::assertSame(1, $interval->d);
        self::assertSame(3, $interval->h);
        self::assertSame(12, $interval->i);
        self::assertSame(5, $interval->s);
        self::assertSame(0, $interval->invert);
        self::assertSame(1, $interval->days);

        self::assertEqualsWithDelta(0.123456, $interval->f, 0.000001);
    }

    public function testNegativeMicrosecondsToDateInterval(): void
    {
        $interval = (Duration::of(microseconds:-5_000_000))->toDateInterval();

        self::assertSame(1, $interval->invert);
        self::assertSame(5, $interval->s);
    }

    public function testToDateIntervalWithRelativeDate(): void
    {
        $duration = Duration::of(weeks: 5, minutes: 32, seconds: 23, microseconds: 456)->negated();
        $pureInterval = $duration->toDateInterval();
        $relativeInterval = $duration->toDateInterval(new DateTimeImmutable(datetime: '2024-01-27', timezone: new DateTimeZone('UTC')));

        self::assertFalse($pureInterval->days);
        self::assertSame(35, $relativeInterval->days);
        self::assertNotEquals($pureInterval->days, $relativeInterval->days);
    }

    public function testZeroMicroseconds(): void
    {
        $duration = Duration::of();

        self::assertSame('0:00:00', $duration->format(DurationFormat::Clock));
        self::assertSame(0, $duration->hours);
        self::assertSame(0, $duration->minutes);
        self::assertSame(0, $duration->seconds);
        self::assertSame(0, $duration->microseconds);
        self::assertSame(0, $duration->daysCount);
        self::assertSame(0, $duration->weeksCount);
        self::assertSame(0, $duration->sign);
        self::assertSame('0s', $duration->format(DurationFormat::Compact));
        self::assertSame('0µs', $duration->format(DurationFormat::Compact, SubSecondDisplay::Always));
        self::assertTrue($duration->isEmpty());
        self::assertEquals($duration, Duration::zero());
    }

    public function test_add_returns_new_instance(): void
    {
        $a = Duration::of(hours: 1);
        $b = Duration::of(minutes: 30);

        self::assertNotSame($a, $a->sum($b));
    }

    public function test_add_single_duration(): void
    {
        $a = Duration::of(hours: 1);
        $b = Duration::of(minutes: 30);

        self::assertSame('1:30:00', $a->sum($b)->format(DurationFormat::Clock));
    }

    public function test_add_multiple_durations(): void
    {
        $base = Duration::of(hours: 1);
        $result = $base->sum(
            Duration::of(minutes: 30),
            Duration::of(seconds: 45),
            Duration::of(microseconds: 123456),
        );

        self::assertSame('1:30:45.123456', $result->format(DurationFormat::Clock));
    }

    public function test_add_negative_duration(): void
    {
        $a = Duration::of(hours: 5);
        $b = Duration::of(hours: -2);

        self::assertSame('3:00:00', $a->sum($b)->format(DurationFormat::Clock));
    }

    public function test_add_result_can_be_negative(): void
    {
        $a = Duration::of(hours: 1);
        $b = Duration::of(hours: -3);

        self::assertSame('-2:00:00', $a->sum($b)->format(DurationFormat::Clock));
    }

    public function test_add_without_arguments_returns_equal_duration(): void
    {
        $duration = Duration::of(hours: 2);

        self::assertSame($duration, $duration->sum());
    }

    public function test_add_preserves_microseconds(): void
    {
        $a = Duration::of(microseconds: 500000);
        $b = Duration::of(microseconds: 250000);

        self::assertSame('0:00:00.750000', $a->sum($b)->format(DurationFormat::Clock));
    }

    public function test_abs_negate(): void
    {
        $duration = Duration::of(microseconds: -500000);

        self::assertEquals($duration, $duration->abs()->negated());
    }

    #[DataProvider('iso8601Provider')]
    public function test_to_iso8601(int $microseconds, string $expected): void
    {
        self::assertSame($expected, Duration::of(microseconds:$microseconds)->format());
    }

    /**
     * @return iterable<non-empty-string, array{0:int, 1:string}>
     */
    public static function iso8601Provider(): iterable
    {
        yield 'zero duration' => [0, 'PT0S'];
        yield 'one second' => [1_000_000, 'PT1S'];
        yield 'one minute' => [60_000_000, 'PT1M'];
        yield 'one hour' => [3_600_000_000, 'PT1H'];
        yield 'hours minutes seconds' => [3_661_000_000, 'PT1H1M1S'];
        yield 'fractional seconds' => [3_661_500_000, 'PT1H1M1.5S'];
        yield 'microseconds precision' => [3_661_000_123, 'PT1H1M1.000123S'];
        yield 'sub second only' => [123, 'PT0.000123S'];
        yield 'trim trailing zeros' => [1_500_000, 'PT1.5S'];
        yield 'negative fractional duration' => [-1_500_000, '-PT1.5S'];
        yield 'negative complex duration' => [-3_661_000_123, '-PT1H1M1.000123S'];
        yield '24 hours duration' => [86_400_000_000, 'P1D'];
    }

    #[DataProvider('truncateProvider')]
    public function test_truncate_to_precision(
        int $microseconds,
        Unit $precision,
        int $expectedMicroseconds,
    ): void {
        self::assertSame(
            $expectedMicroseconds,
            Duration::of(microseconds:$microseconds)
                ->truncateTo($precision)
                ->total(Unit::Microsecond),
        );
    }

    /**
     * @return iterable<non-empty-string, array{0: int, 1: Unit, 2: int}>
     */
    public static function truncateProvider(): iterable
    {
        // 1h 1m 1s + 500ms = 3_661_500_000 µs
        yield 'truncate to seconds removes microseconds' => [
            3_661_500_000,
            Unit::Second,
            3_661_000_000,
        ];

        yield 'truncate to minutes removes seconds and microseconds' => [
            3_661_500_000,
            Unit::Minute,
            3_660_000_000,
        ];

        yield 'truncate to hours removes minutes seconds and microseconds' => [
            3_661_500_000,
            Unit::Hour,
            3_600_000_000,
        ];

        yield 'zero duration stays zero' => [
            0,
            Unit::Second,
            0,
        ];

        yield 'already clean seconds unchanged' => [
            1_000_000,
            Unit::Second,
            1_000_000,
        ];

        yield 'negative duration is preserved when inverted' => [
            -3_661_500_000,
            Unit::Minute,
            -3_660_000_000,
        ];
    }

    #[DataProvider('truncateImmutabilityProvider')]
    public function test_truncate_is_immutable(
        int $microseconds,
        Unit $precision,
    ): void {
        $duration = Duration::of(microseconds:$microseconds);

        $result = $duration->truncateTo($precision);

        self::assertNotSame($duration, $result);
    }

    /**
     * @return iterable<array{0: int, 1: Unit}>
     */
    public static function truncateImmutabilityProvider(): iterable
    {
        yield [3_661_500_000, Unit::Second];
        yield [3_661_500_000, Unit::Minute];
        yield [-3_661_500_000, Unit::Hour];
    }

    public function test_truncate_preserves_sign_consistency(): void
    {
        $positive = Duration::of(microseconds:3_661_500_000);
        $negative = Duration::of(microseconds:-3_661_500_000);

        self::assertTrue($positive->truncateTo(Unit::Minute)->total(Unit::Microsecond) > 0);
        self::assertTrue($negative->truncateTo(Unit::Minute)->total(Unit::Microsecond) < 0);
    }

    #[TestWith([PHP_INT_MIN], 'overflow with lower bound integer')]
    #[TestWith([PHP_INT_MAX], 'overflow with upper bound integer')]
    public function test_it_can_not_invert_all_values(int $bound): void
    {
        $this->expectException(InvalidDuration::class);
        $this->expectExceptionMessage('The duration exceeds the supported range.');

        Duration::of(microseconds:$bound)->negated();
    }

    /* -------------------------------------------------
     * compareTo
     * ------------------------------------------------- */

    #[DataProvider('compareProvider')]
    public function test_compare_to(
        Duration $left,
        Duration $right,
        int $expected,
    ): void {
        self::assertSame($expected, $left->compareTo($right));
    }

    /**
     * @throws InvalidDuration
     * @return iterable<non-empty-string, array{0: Duration, 1: Duration}>
     */
    public static function compareProvider(): iterable
    {
        yield 'equal durations' => [
            Duration::of(hours: 1),
            Duration::of(minutes: 60),
            0,
        ];

        yield 'lesser duration' => [
            Duration::of(minutes: 30),
            Duration::of(hours: 1),
            -1,
        ];

        yield 'greater duration' => [
            Duration::of(hours: 2),
            Duration::of(hours: 1),
            1,
        ];

        yield 'negative vs positive' => [
            Duration::of(hours: -1),
            Duration::of(hours: 1),
            -1,
        ];
    }

    /* -------------------------------------------------
     * equals
     * ------------------------------------------------- */

    public function test_equals_returns_true_for_equal_duration(): void
    {
        self::assertTrue(Duration::of(hours: 1)->equals(Duration::of(minutes: 60)));
    }

    public function test_equals_returns_false_for_different_duration(): void
    {
        self::assertFalse(Duration::of(hours: 1)->equals(Duration::of(minutes: 59)));
    }

    /* -------------------------------------------------
     * isGreaterThan
     * ------------------------------------------------- */

    public function test_is_greater_than(): void
    {
        self::assertTrue(Duration::of(hours: 2)->isLongerThan(Duration::of(hours: 1)));
        self::assertFalse(Duration::of(hours: 1)->isLongerThan(Duration::of(hours: 2)));
    }

    /* -------------------------------------------------
     * isGreaterThanOrEqual
     * ------------------------------------------------- */

    public function test_is_greater_than_or_equal(): void
    {
        self::assertTrue(Duration::of(hours: 2)->isLongerThanOrEqual(Duration::of(hours: 1)));
        self::assertTrue(Duration::of(hours: 1)->isLongerThanOrEqual(Duration::of(minutes: 60)));
        self::assertFalse(Duration::of(minutes: 30)->isLongerThanOrEqual(Duration::of(hours: 1)));
    }

    /* -------------------------------------------------
     * isLesserThan
     * ------------------------------------------------- */

    public function test_is_lesser_than(): void
    {
        self::assertTrue(Duration::of(minutes: 30)->isShorterThan(Duration::of(hours: 1)));
        self::assertFalse(Duration::of(hours: 2)->isShorterThan(Duration::of(hours: 1)));
    }

    /* -------------------------------------------------
     * isLesserThanOrEqual
     * ------------------------------------------------- */

    public function test_is_lesser_than_or_equal(): void
    {
        self::assertTrue(Duration::of(minutes: 30)->isShorterThanOrEqual(Duration::of(hours: 1)));
        self::assertTrue(Duration::of(hours: 1)->isShorterThanOrEqual(Duration::of(minutes: 60)));
        self::assertFalse(Duration::of(hours: 2)->isShorterThanOrEqual(Duration::of(hours: 1)));
    }

    /* -------------------------------------------------
     * with
     * ------------------------------------------------- */

    #[DataProvider('withProvider')]
    public function test_with(
        Duration $initial,
        int $hours,
        int $minutes,
        int $seconds,
        int $microseconds,
        string $expected,
    ): void {
        $result = $initial->increment(hours: $hours, minutes: $minutes, seconds: $seconds, microseconds: $microseconds);

        self::assertSame($expected, $result->format(DurationFormat::Clock));
    }

    /**
     * @throws InvalidDuration
     * @return iterable<non-empty-string, array{0: Duration, 1: ?int, 2: ?int, 3: ?int, 4: ?int, 5: non-empty-string}>
     */
    public static function withProvider(): iterable
    {
        $base = Duration::of(
            hours: 12,
            minutes: 34,
            seconds: 56,
            microseconds: 123456,
        );

        yield 'replace hours' => [
            $base,
            1,
            0,
            0,
            0,
            '13:34:56.123456',
        ];

        yield 'replace minutes' => [
            $base,
            0,
            10,
            0,
            0,
            '12:44:56.123456',
        ];

        yield 'replace seconds' => [
            $base,
            0,
            0,
            5,
            0,
            '12:35:01.123456',
        ];

        yield 'replace microseconds' => [
            $base,
            0,
            0,
            0,
            1,
            '12:34:56.123457',
        ];

        yield 'replace multiple values' => [
            $base,
            1,
            2,
            3,
            4,
            '13:36:59.123460',
        ];
    }

    public function test_with_preserves_original_instance(): void
    {
        $duration = Duration::of(hours: 10);
        $modified = $duration->increment(hours: 5);

        self::assertSame('10:00:00', $duration->format(DurationFormat::Clock));
        self::assertSame('15:00:00', $modified->format(DurationFormat::Clock));
    }

    public function test_with_returns_same_instance_when_called_without_arguments(): void
    {
        $duration = Duration::of(hours: 1);

        self::assertSame($duration, $duration->increment());
    }

    public function testItParsesSimpleMinutes(): void
    {
        $duration = Duration::fromIso8601('PT30M');

        self::assertSame('PT30M', $duration->format());
    }

    public function testItParsesHoursMinutesSeconds(): void
    {
        $duration = Duration::fromIso8601('PT1H30M15S');

        self::assertSame('PT1H30M15S', $duration->format());
    }

    public function testItParsesFractionalSeconds(): void
    {
        $duration = Duration::fromIso8601('PT0.5S');

        self::assertSame('PT0.5S', $duration->format());
    }

    public function testItParsesDays(): void
    {
        $duration = Duration::fromIso8601('P2DT3H');

        self::assertSame('P2DT3H', $duration->format());
    }

    public function testItParsesNegativeDuration(): void
    {
        $duration = Duration::fromIso8601('-PT30S');

        self::assertSame('-PT30S', $duration->format());
    }

    public function testItParseAndNormalizeDuration(): void
    {
        $rawIso8601 = '-PT25H0.5S';
        $duration = Duration::fromIso8601($rawIso8601);

        self::assertNotSame($rawIso8601, $duration->format());
        self::assertSame('-P1DT1H0.5S', $duration->format());
        self::assertSame(1, $duration->daysCount);
    }

    public function testItRejectsYears(): void
    {
        $this->expectException(InvalidDuration::class);
        $this->expectExceptionMessage('The submitted duration `P1Y` contains unsupported ISO 8601 duration components.');

        Duration::fromIso8601('P1Y');
    }

    public function testItRejectsMonths(): void
    {
        $this->expectException(InvalidDuration::class);
        $this->expectExceptionMessage('The submitted duration `P1M` contains unsupported ISO 8601 duration components.');

        Duration::fromIso8601('P1M');
    }

    public function testItRejectsEmptyTimeDesignator(): void
    {
        $this->expectException(InvalidDuration::class);
        $this->expectExceptionMessage('The submitted duration `PT` is not a valid ISO 8601 duration.');

        Duration::fromIso8601('PT');
    }

    public function testItRejectsCompletelyInvalidString(): void
    {
        $this->expectException(InvalidDuration::class);
        $this->expectExceptionMessage('The submitted duration `invalid` is not a valid ISO 8601 duration.');

        Duration::fromIso8601('invalid');
    }

    public function testItParsesWeeks(): void
    {
        $duration = Duration::fromIso8601('P2W');

        self::assertSame('P14D', $duration->format());
    }

    public function testItParsesWeeksAndDays(): void
    {
        $duration = Duration::fromIso8601('P1W2D');

        self::assertSame('P9D', $duration->format());
        self::assertSame(9, $duration->daysCount);
    }

    public function testItParsesNegativeWeeks(): void
    {
        $duration = Duration::fromIso8601('-P3W');

        self::assertSame('-P21D', $duration->format());
    }

    public function testItParsesWeeksWithTimeComponents(): void
    {
        $duration = Duration::fromIso8601('P1WT2H30M');

        self::assertSame('P7DT2H30M', $duration->format());
    }

    public function testItRejectsEmptyWeekNotation(): void
    {
        $this->expectException(InvalidDuration::class);
        $this->expectExceptionMessage('The submitted duration `PW` is not a valid ISO 8601 duration.');

        Duration::fromIso8601('PW');
    }

    public function test_predefined_instances(): void
    {
        $max = Duration::max();
        $min = Duration::min();
        $zero = Duration::zero();

        self::assertTrue($max->isLongerThan($min));
        self::assertTrue($max->isLongerThan($zero));
        self::assertTrue($zero->isLongerThanOrEqual($min));
        self::assertTrue($min->isShorterThan($zero));
    }

    public function testItRejectsInvalidMultiply(): void
    {
        $this->expectException(InvalidDuration::class);

        Duration::max()->multipliedBy(3);
    }

    public function testItRejectsDivideByZero(): void
    {
        $this->expectException(InvalidDuration::class);

        Duration::max()->dividedBy(0);
    }

    public function testItMultiplyTheDuration(): void
    {
        self::assertSame('PT4H', Duration::of(hours: 2)->multipliedBy(2)->format());
        self::assertSame('PT4M', Duration::of(minutes: 2)->multipliedBy(2)->format());
    }

    public function test_duration_can_be_serialized_and_unserialized(): void
    {
        $duration = Duration::fromIso8601('-PT23H30S');
        $restored = unserialize(serialize($duration));

        self::assertInstanceOf(Duration::class, $restored);
        self::assertEquals($duration, $restored);
    }

    public function test_duration_can_be_json_serialized(): void
    {
        $duration = Duration::of(hours: 2, seconds: 35);

        self::assertSame('"PT2H35S"', json_encode($duration));
    }

    #[DataProvider('roundToProvider')]
    public function test_round_to(int $input, Unit $precision, int $expected): void
    {
        $duration = Duration::of(microseconds:  $input);

        self::assertSame($expected, $duration->roundTo($precision)->total(Unit::Microsecond));
    }

    /**
     * @return array<non-empty-string, array{0: int, 1: Unit, 2: int}>
     */
    public static function roundToProvider(): array
    {
        return [
            // [input microseconds, precision, expected microseconds]

            // seconds
            'round down seconds' => [1_499_999, Unit::Second, 1_000_000],
            'round up seconds'   => [1_500_000, Unit::Second, 2_000_000],
            'exact seconds'      => [2_000_000, Unit::Second, 2_000_000],

            // minutes
            'round down minutes' => [89_000_000, Unit::Minute, 60_000_000],
            'round up minutes'   => [91_000_000, Unit::Minute, 120_000_000],

            // hours
            'round hours'        => [3_500_000_000, Unit::Hour, 3_600_000_000],

            // days
            'round days'         => [86_000_000_000, Unit::Day, 86_400_000_000],

            // negative values
            'negative round up'  => [-1_500_000, Unit::Second, -2_000_000],
            'negative round down' => [-1_499_999, Unit::Second, -1_000_000],

            // micro boundary (identity case)
            'micro unchanged'    => [999, Unit::Microsecond, 999],
        ];
    }

    /**
     * @param list<Duration> $durations
     *
     * @throws InvalidTime
     */
    #[DataProvider('minOfProvider')]
    public function testMinOf(array $durations, Duration $expected): void
    {
        self::assertTrue(Duration::minOf(...$durations)->equals($expected));
    }

    /**
     * @throws InvalidDuration
     * @return array<non-empty-string, array{0: list<Duration>, 1: Duration}>
     */
    public static function minOfProvider(): array
    {
        return [
            'simple case' => [
                [
                    Duration::of(seconds: 10),
                    Duration::of(seconds: 5),
                    Duration::of(seconds: 8),
                ],
                Duration::of(seconds: 5),
            ],

            'mixed units' => [
                [
                    Duration::of(minutes: 1),
                    Duration::of(seconds: 30),
                    Duration::of(seconds: 90),
                ],
                Duration::of(seconds: 30),
            ],
        ];
    }

    /**
     * @param list<Duration> $durations
     *
     * @throws InvalidTime
     */
    #[DataProvider('maxOfProvider')]
    public function testMaxOf(array $durations, Duration $expected): void
    {
        self::assertTrue(Duration::maxOf(...$durations)->equals($expected));
    }

    /**
     * @throws InvalidDuration
     * @return array<non-empty-string, array{0: list<Duration>, 1: Duration}>
     */
    public static function maxOfProvider(): array
    {
        return [
            'simple case' => [
                [
                    Duration::of(seconds: 10),
                    Duration::of(seconds: 5),
                    Duration::of(seconds: 8),
                ],
                Duration::of(seconds: 10),
            ],
        ];
    }

    #[DataProvider('clampProvider')]
    public function testClamp(Duration $value, Duration $min, Duration $max, Duration $expected): void
    {
        self::assertTrue($value->clamp($min, $max)->equals($expected));
    }

    /**
     * @throws InvalidDuration
     * @return array<non-empty-string, list<Duration>>
     */
    public static function clampProvider(): array
    {
        return [
            'below range' => [
                Duration::of(seconds: 2),
                Duration::of(seconds: 5),
                Duration::of(seconds: 10),
                Duration::of(seconds: 5),
            ],

            'above range' => [
                Duration::of(seconds: 20),
                Duration::of(seconds: 5),
                Duration::of(seconds: 10),
                Duration::of(seconds: 10),
            ],

            'inside range' => [
                Duration::of(seconds: 7),
                Duration::of(seconds: 5),
                Duration::of(seconds: 10),
                Duration::of(seconds: 7),
            ],

            'edge boundaries' => [
                Duration::of(seconds: 5),
                Duration::of(seconds: 5),
                Duration::of(seconds: 10),
                Duration::of(seconds: 5),
            ],
        ];
    }

    #[DataProvider('validIntervalsProvider')]
    public function testFromDateIntervalConvertsCorrectly(DateInterval $interval, int $expectedMicroseconds): void
    {
        self::assertSame($expectedMicroseconds, Duration::fromDateInterval($interval)->total(Unit::Microsecond));
    }

    /**
     * @return array<non-empty-string, array{interval: DateInterval, expectedMicroseconds: int}>
     */
    public static function validIntervalsProvider(): array
    {
        return [
            'simple positive' => [
                'interval' => new DateInterval('P1DT2H3M4S'),
                'expectedMicroseconds' => ((1 * 86400) + (2 * 3600) + (3 * 60) + 4) * 1_000_000,
            ],

            'negative interval' => [
                'interval' => self::diff('-PT1H30M'),
                'expectedMicroseconds' => -((1 * 3600) + (30 * 60)) * 1_000_000,
            ],

            'with microseconds' => [
                'interval' => self::fromSpec('PT0S', 500_000),
                'expectedMicroseconds' => 500_000,
            ],

            'days from diff (days populated)' => [
                'interval' => self::diff('P2D'),
                'expectedMicroseconds' => -2 * 86400 * 1_000_000,
            ],
        ];
    }

    private static function diff(string $spec): DateInterval
    {
        $now = new DateTimeImmutable();

        $res = $now->add(new DateInterval(ltrim($spec, '-')))->diff($now);

        return $res;
    }

    private static function fromSpec(string $spec, int $microseconds): DateInterval
    {
        $sign = 0;
        if (str_starts_with($spec, '-')) {
            $spec = substr($spec, 1);
            $sign = 1;
        }

        $interval = new DateInterval($spec);
        if (1 === $sign) {
            $interval->invert = 1;
        }

        if (0 !== $microseconds) {
            $interval->f = $microseconds / 1_000_000;
        }

        return $interval;
    }

    #[DataProvider('invalidIntervalsProvider')]
    public function testFromDateIntervalThrowsForInvalidIntervals(DateInterval $interval): void
    {
        $this->expectException(InvalidDuration::class);

        Duration::fromDateInterval($interval);
    }

    /**
     * @return array<non-empty-string, array{0: DateInterval}>
     */
    public static function invalidIntervalsProvider(): array
    {
        return [
            'has years' => [
                new DateInterval('P1Y'),
            ],

            'has months' => [
                new DateInterval('P2M'),
            ],

            'years and days mixed' => [
                new DateInterval('P1Y2DT3H'),
            ],
        ];
    }

    public function test_diffrent_date_intervals(): void
    {
        $a = self::diff('P3DT4H');
        $b = new DateInterval('P3DT4H');

        self::assertNotEquals(Duration::fromDateInterval($a), Duration::fromDateInterval($b));
    }

    public function test_diff_different_date_intervals_when_deterministic(): void
    {
        $nonDeterministic = new DateInterval('P1M1D');
        $a = new DateTimeImmutable('2025-05-03 12:34:56');
        $b = $a->add($nonDeterministic);

        $duration = Duration::fromDateInterval($a->diff($b));
        self::assertTrue($duration->isLongerThan(Duration::of(days: 30)));

        $this->expectException(InvalidDuration::class);
        Duration::fromDateInterval($nonDeterministic);
    }
}
