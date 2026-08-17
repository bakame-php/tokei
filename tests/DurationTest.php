<?php

declare(strict_types=1);

namespace Bakame\Tokei\Tests;

use Bakame\Stackwatch\DurationUnit;
use Bakame\Tokei\Duration;
use Bakame\Tokei\DurationFormat;
use Bakame\Tokei\Internal\DurationComponents;
use Bakame\Tokei\Internal\DurationParts;
use Bakame\Tokei\Internal\InputNormalizer;
use Bakame\Tokei\Internal\Parser;
use Bakame\Tokei\Internal\UnitTransformer;
use Bakame\Tokei\InvalidDuration;
use Bakame\Tokei\InvalidTime;
use Bakame\Tokei\SnapMode;
use Bakame\Tokei\TokeiException;
use Bakame\Tokei\Unit;
use DateInterval;
use DateTime;
use DateTimeImmutable;
use DivisionByZeroError;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Time\Duration as TimeDuration;
use ValueError;
use function json_encode;
use function ltrim;
use function serialize;
use function substr;
use function unserialize;

#[CoversClass(InvalidDuration::class)]
#[CoversClass(Duration::class)]
#[CoversClass(DurationFormat::class)]
#[CoversClass(DurationParts::class)]
#[CoversClass(Parser::class)]
#[CoversClass(DurationComponents::class)]
#[CoversClass(Unit::class)]
#[CoversClass(UnitTransformer::class)]
#[CoversClass(InputNormalizer::class)]
final class DurationTest extends TestCase
{
    public function testParseMicroseconds(): void
    {
        $duration = Duration::of(weeks: 5, days: 6, hours: 2, minutes: 15, seconds: 42, microseconds: 123_456);

        self::assertFalse($duration->isZero());
        self::assertFalse($duration->negative);
        self::assertSame(3_550_542_123_456, $duration->in(Unit::Microsecond));
        self::assertSame('5w6d2h15m42s123456µs', $duration->format(DurationFormat::Compact));
        self::assertSame(123_456_000, $duration->nanoseconds);
        self::assertSame(3_550_542, $duration->seconds);
    }

    public function testParseNegativeMicroseconds(): void
    {
        $duration = Duration::of(microseconds: 1_500_000)->negate();

        self::assertTrue($duration->negative);
        self::assertSame('-1s500ms', $duration->format(DurationFormat::Compact));
        self::assertSame(500_000_000, $duration->nanoseconds);
        self::assertSame(1, $duration->seconds);
    }

    public function testFormatMicrosecondsWithoutFraction(): void
    {
        self::assertSame('09:25:00', Duration::of(hours: 9, minutes: 25)->format(DurationFormat::Timer));
    }

    public function testFormatMicrosecondsWithFraction(): void
    {
        self::assertSame('01:02:03.000045', Duration::of(hours: 1, minutes: 2, seconds: 3, microseconds: 45)->format(DurationFormat::Timer));
    }

    public function testFormatNegativeMicroseconds(): void
    {
        self::assertSame('-04:05:06', Duration::of(hours: 4, minutes: 5, seconds: 6)->negate()->format(DurationFormat::Timer));
    }

    public function testZeroMicroseconds(): void
    {
        $duration = Duration::of();

        self::assertSame('00:00:00', $duration->format(DurationFormat::Timer));
        self::assertTrue($duration->isZero());
        self::assertSame(0, $duration->in(Unit::Microsecond));
        self::assertSame('0s', $duration->format(DurationFormat::Compact));
        self::assertTrue($duration->isZero());
        self::assertFalse($duration->negative);
        self::assertTrue($duration->equals(Duration::zero()));
    }

    public function test_add_returns_new_instance(): void
    {
        $a = Duration::of(hours: 1);
        $b = Duration::of(minutes: 30);

        self::assertNotSame($a, $a->add($b));
    }

    public function test_add_single_duration(): void
    {
        $a = Duration::of(hours: 1);
        $b = Duration::of(minutes: 30);

        self::assertSame('01:30:00', $a->add($b)->format(DurationFormat::Timer));
    }

    public function test_add_multiple_durations(): void
    {
        $result = Duration::of(hours: 1)
            ->add(
                Duration::of(minutes: 30),
                Duration::of(seconds: 45),
                Duration::of(microseconds: 123456),
            );

        self::assertSame('01:30:45.123456', $result->format(DurationFormat::Timer));
    }

    public function test_sum_with_no_arguments(): void
    {
        $duration =  Duration::of(hours: 1);

        self::assertTrue($duration->add()->equals($duration));
    }

    public function test_add_negative_duration(): void
    {
        $a = Duration::of(hours: 5);
        $b = Duration::of(hours: 2)->negate();

        self::assertSame('03:00:00', $a->add($b)->format(DurationFormat::Timer));
    }

    public function test_add_result_can_be_negative(): void
    {
        $a = Duration::of(hours: 1);
        $b = Duration::of(hours: 3)->negate();

        self::assertSame('-02:00:00', $a->add($b)->format(DurationFormat::Timer));
    }

    public function test_add_preserves_microseconds(): void
    {
        $a = Duration::of(microseconds: 500000);
        $b = Duration::of(microseconds: 250000);

        self::assertSame('00:00:00.750', $a->add($b)->format(DurationFormat::Timer));
    }

    public function test_abs_negate(): void
    {
        $duration = Duration::of(microseconds: 500000)->negate();

        self::assertTrue($duration->equals($duration->absolute()->negate()));
    }

    #[DataProvider('iso8601Provider')]
    public function test_to_iso8601(int $microseconds, string $expected): void
    {
        $duration = 0 > $microseconds
            ? Duration::of(microseconds: -$microseconds)->negate()
            : Duration::of(microseconds: $microseconds);

        self::assertSame($expected, $duration->format(DurationFormat::Iso8601));
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
        yield '24 hours duration' => [86_400_000_000, 'PT24H'];
    }

    #[DataProvider('truncateProvider')]
    public function test_truncate_to_precision(
        int $microseconds,
        Unit $unit,
        int $expectedMicroseconds,
    ): void {

        $duration = 0 > $microseconds
            ? Duration::of(microseconds: -$microseconds)->negate()
            : Duration::of(microseconds: $microseconds);

        self::assertSame(
            $expectedMicroseconds,
            $duration
                ->roundTo($unit, SnapMode::Floor)
                ->in(Unit::Microsecond)
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
           -3_720_000_000,
        ];
    }

    /**
     * @throws InvalidDuration
     */
    #[DataProvider('truncateImmutabilityProvider')]
    public function test_truncate_is_immutable(
        int $microseconds,
        Unit $unit,
    ): void {
        $duration = 0 > $microseconds
            ? Duration::of(microseconds: -$microseconds)->negate()
            : Duration::of(microseconds:$microseconds);

        $result = $duration->roundTo($unit, SnapMode::Floor);

        self::assertNotSame($duration, $result);
    }

    /**
     * @return iterable<array{0: non-negative-int, 1: Unit}>
     */
    public static function truncateImmutabilityProvider(): iterable
    {
        yield [3_661_500_000, Unit::Second];
        yield [3_661_500_000, Unit::Minute];
        //yield [-3_661_500_000, Unit::Hour];
    }

    public function test_truncate_preserves_sign_consistency(): void
    {
        $positive = Duration::of(microseconds:3_661_500_000);
        $negative = Duration::of(microseconds:3_661_500_000)->negate();

        self::assertTrue($positive->roundTo(Unit::Minute, SnapMode::Floor)->in(Unit::Microsecond) > 0);
        self::assertTrue($negative->roundTo(Unit::Minute, SnapMode::Floor)->in(Unit::Microsecond) < 0);
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
        self::assertSame($expected, Duration::compare($left, $right));
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
            Duration::of(hours: 1)->negate(),
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

    /**
     * @param non-negative-int $hours
     * @param non-negative-int $minutes
     * @param non-negative-int $seconds
     * @param non-negative-int $microseconds
     *
     * @throws InvalidDuration
     */
    #[DataProvider('withProvider')]
    public function test_increment(
        Duration $initial,
        int $hours,
        int $minutes,
        int $seconds,
        int $microseconds,
        string $expected,
    ): void {
        $result = $initial->add(Duration::of(hours: $hours, minutes: $minutes, seconds: $seconds, microseconds: $microseconds));

        self::assertSame($expected, $result->format(DurationFormat::Timer));
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

    public function test_increment_preserves_original_instance(): void
    {
        $duration = Duration::of(hours: 10);
        $modified = $duration->add(Duration::of(hours: 5));

        self::assertSame('10:00:00', $duration->format(DurationFormat::Timer));
        self::assertSame('15:00:00', $modified->format(DurationFormat::Timer));
    }

    public function test_decrement_preserves_original_instance(): void
    {
        $duration = Duration::of(hours: 10);
        $modified = $duration->sub(Duration::of(hours: 5));

        self::assertSame('10:00:00', $duration->format(DurationFormat::Timer));
        self::assertSame('05:00:00', $modified->format(DurationFormat::Timer));
    }

    public function test_increment_returns_same_instance_when_called_without_arguments(): void
    {
        $duration = Duration::of(hours: 1);

        self::assertSame($duration, $duration->add());
    }

    public function test_decrement_returns_same_instance_when_called_without_arguments(): void
    {
        $duration = Duration::of(hours: 1);

        self::assertSame($duration, $duration->sub());
    }

    public function testItParsesSimpleMinutes(): void
    {
        $duration = Duration::fromFormat('PT30M', DurationFormat::Iso8601);

        self::assertSame('PT30M', $duration->format(DurationFormat::Iso8601));
    }

    public function testItParsesHoursMinutesSeconds(): void
    {
        $duration = Duration::fromFormat('PT1H30M15S', DurationFormat::Iso8601);

        self::assertSame('PT1H30M15S', $duration->format(DurationFormat::Iso8601));
    }

    public function testItParsesFractionalSeconds(): void
    {
        $duration = Duration::fromFormat('PT0.5S', DurationFormat::Iso8601);

        self::assertSame('PT0.5S', $duration->format(DurationFormat::Iso8601));
    }

    public function testItParsesDays(): void
    {
        $duration = Duration::fromFormat('P2DT3H', DurationFormat::Iso8601);

        self::assertSame('PT51H', $duration->format(DurationFormat::Iso8601));
    }

    public function testItParsesNegativeDuration(): void
    {
        $duration = Duration::fromFormat('-PT30S', DurationFormat::Iso8601);

        self::assertSame('-PT30S', $duration->format(DurationFormat::Iso8601));
    }

    public function testItParseAndNormalizeDuration(): void
    {
        $rawIso8601 = '-PT25H0.5S';
        $duration = Duration::fromFormat($rawIso8601, DurationFormat::Iso8601);

        self::assertSame($rawIso8601, $duration->format(DurationFormat::Iso8601));
    }

    public function testItRejectsYears(): void
    {
        $this->expectException(InvalidDuration::class);

        Duration::fromFormat('P1Y', DurationFormat::Iso8601);
    }

    public function testItRejectsMonths(): void
    {
        $this->expectException(InvalidDuration::class);

        Duration::fromFormat('P1M', DurationFormat::Iso8601);
    }

    public function testItRejectsEmptyTimeDesignator(): void
    {
        $this->expectException(InvalidDuration::class);

        Duration::fromFormat('PT', DurationFormat::Iso8601);
    }

    public function testItRejectsCompletelyInvalidString(): void
    {
        $this->expectException(InvalidDuration::class);

        Duration::fromFormat('invalid', DurationFormat::Iso8601);
    }

    #[TestWith(['PT1.0000000001S', DurationFormat::Iso8601])]
    #[TestWith(['00:00:00"0000000001', DurationFormat::Timer])]
    public function testItRejectsInvalidDurationWithSuNanosecondsPrecision(string $notation, DurationFormat $format): void
    {
        $this->expectException(InvalidDuration::class);

        Duration::fromFormat($notation, $format);
    }

    public function testItCanRepresentsNegativeDuration(): void
    {
        self::assertSame(
            '-4w3d2s1µs',
            Duration::of(weeks: 4, days: 3, seconds: 2, microseconds: 1)
                ->negate()
                ->format(DurationFormat::Compact)
        );
    }

    public function testItParsesWeeks(): void
    {
        $duration = Duration::fromFormat('P2W', DurationFormat::Iso8601);

        self::assertSame('PT336H', $duration->format(DurationFormat::Iso8601));
    }

    public function testItParsesWeeksAndDays(): void
    {
        $duration = Duration::fromFormat('P1W2D', DurationFormat::Iso8601);

        self::assertSame('PT216H', $duration->format(DurationFormat::Iso8601));
    }

    public function testItParsesNegativeWeeks(): void
    {
        $duration = Duration::fromFormat('-P3W', DurationFormat::Iso8601);

        self::assertSame('-PT504H', $duration->format(DurationFormat::Iso8601));
    }

    public function testItParsesWeeksWithTimeComponents(): void
    {
        $duration = Duration::fromFormat('P1WT2H30M', DurationFormat::Iso8601);

        self::assertSame('PT170H30M', $duration->format(DurationFormat::Iso8601));
    }

    public function testItRejectsEmptyWeekNotation(): void
    {
        $this->expectException(InvalidDuration::class);

        Duration::fromFormat('PW', DurationFormat::Iso8601);
    }

    #[Test]
    public function it_can_parse_from_duration_format(): void
    {
        self::assertTrue(Duration::fromFormat('0 n', DurationFormat::LargestUnit)->equals(Duration::zero()));
        self::assertTrue(Duration::fromFormat('0 n', DurationFormat::LargestUnit)->equals(Duration::zero()));
        self::assertTrue(Duration::fromFormat('0 N', DurationFormat::LargestUnit)->equals(Duration::zero()));
        self::assertTrue(Duration::fromFormat('1 us', DurationFormat::LargestUnit)->equals(Duration::fromMicroseconds(1)));
        self::assertTrue(Duration::fromFormat('1.0 ms', DurationFormat::LargestUnit)->equals(Duration::fromMilliseconds(1)));
        self::assertTrue(Duration::fromFormat('1.0 s', DurationFormat::LargestUnit)->equals(Duration::fromSeconds(1)));
        self::assertTrue(Duration::fromFormat('1    Min', DurationFormat::LargestUnit)->equals(Duration::fromMinutes(1)));
        self::assertTrue(Duration::fromFormat('1.00 min', DurationFormat::LargestUnit)->equals(Duration::fromMinutes(1)));
        self::assertTrue(Duration::fromFormat('24.0 h', DurationFormat::LargestUnit)->equals(Duration::fromDays(1)));
        self::assertTrue(Duration::fromFormat('2 WEEKS', DurationFormat::LargestUnit)->equals(Duration::fromWeeks(2)));
        self::assertTrue(
            Duration::fromFormat('21.58 days', DurationFormat::LargestUnit)->equals(
                Duration::fromFormat('PT517H55M12S', DurationFormat::Iso8601)
            )
        );
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

        Duration::max()->multiplyBy(3);
    }

    public function testItRejectsDivideByZero(): void
    {
        $this->expectException(DivisionByZeroError::class);

        Duration::max()->divideBy(0);
    }

    public function testItMultiplyTheDuration(): void
    {
        self::assertSame('PT4H', Duration::of(hours: 2)->multiplyBy(2)->format(DurationFormat::Iso8601));
        self::assertSame('PT4M', Duration::of(minutes: 2)->multiplyBy(2)->format(DurationFormat::Iso8601));
    }

    public function test_duration_can_be_serialized_and_unserialized(): void
    {
        $duration = Duration::fromFormat('-PT23H30S', DurationFormat::Iso8601);
        $restored = unserialize(serialize($duration));

        self::assertInstanceOf(Duration::class, $restored);
        self::assertTrue($duration->equals($restored));
    }

    public function test_duration_can_be_json_serialized(): void
    {
        $duration = Duration::of(hours: 2, seconds: 35);

        self::assertSame('"PT2H35S"', json_encode($duration));
    }

    #[DataProvider('roundToProvider')]
    public function test_round_to(int $input, Unit $precision, int $expected): void
    {
        $duration = 0 > $input
            ? Duration::of(microseconds: -$input)->negate()
            : Duration::of(microseconds: $input);

        self::assertSame($expected, $duration->roundTo($precision)->in(Unit::Microsecond));
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
     * @return Duration
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
        self::assertSame($expectedMicroseconds, Duration::fromDateInterval($interval)->in(Unit::Microsecond));
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

    /**
     * @param non-negative-int|null $milliseconds
     *
     * @throws InvalidDuration
     */
    #[DataProvider('validClocks')]
    public function test_clock_factory(string $data, int $seconds, ?int $milliseconds = 0): void
    {
        $duration = 0 > $seconds
            ? Duration::of(seconds: -$seconds, milliseconds: $milliseconds ?? 0)->negate()
            : Duration::of(seconds: $seconds, milliseconds: $milliseconds ?? 0);

        self::assertTrue(Duration::fromFormat($data, DurationFormat::Timer)->equals($duration));
    }

    /**
     * @return array<non-empty-string, array{0: non-empty-string, 1: int, 2?: int}>
     */
    public static function validClocks(): array
    {
        return [
            'zero' => ['00:00:00', 0],
            'simple' => ['01:02:03', 3723],
            'simple with spaces' => ['01 : 02   : 03   ', 3723],
            'midnight edge' => ['00:00:01', 1],
            'large hours' => ['100:00:00', 360000],
            'microseconds with dot' => ['01:02:03.500000', 3723, 500],
            'microseconds with enclosure character' => ['01:02:03"500000', 3723, 500],
        ];
    }

    #[DataProvider('invalidClocks')]
    public function test_invalid_clock_factory(string $value): void
    {
        $this->expectException(InvalidDuration::class);
        Duration::fromFormat($value, DurationFormat::Timer);
    }

    /**
     * @return array<non-empty-string, array{0:string}>
     */
    public static function invalidClocks(): array
    {
        return [
            'mm:ss format' => ['12:34'],
            'too many parts' => ['01:02:03:04'],
            'missing seconds' => ['01:02'],
            'invalid seconds' => ['01:02:60'],
            'invalid minutes' => ['01:60:59'],
            'invalid microseconds' => ['01:59:59.10000000000'],
            'letters' => ['aa:bb:cc'],
            'empty' => [''],
            'wrong separator' => ['01-02-03'],
        ];
    }

    /**
     * @param non-negative-int|null $microseconds
     *
     * @throws InvalidDuration
     */
    #[DataProvider('validCompactNotation')]
    public function test_compact_factory(string $value, int $seconds, ?int $microseconds = 0): void
    {
        $duration = 0 > $seconds
            ? Duration::of(seconds: -$seconds, microseconds: $microseconds ?? 0)->negate()
            : Duration::of(seconds: $seconds, microseconds: $microseconds ?? 0);

        self::assertTrue(Duration::fromFormat($value, DurationFormat::Compact)->equals($duration));
    }

    /**
     * @return array<non-empty-string, array{0: non-empty-string, 1: int, 2?: int}>
     */
    public static function validCompactNotation(): array
    {
        return [
            'seconds only' => ['5s', 5],
            'minutes seconds' => ['1m 30s', 90],
            'hours' => ['2h', 7200],
            'full' => ['1w 2d 3h 4m 5s', 788645],
            'whitespace flexible' => ['1w   3h    5s', 1 * 604800 + 3 * 3600 + 5],
            'microseconds' => ['1s 250µs', 1, 250],
            'microseconds with u instead of micron' => ['1s 250us', 1,  250],
            'negative' => ['-1h 30m', -5400],
            'zero' => ['0s', 0],
        ];
    }

    #[DataProvider('invalidCompactNotation')]
    public function test_invalid_compact_factory(string $value): void
    {
        $this->expectException(InvalidDuration::class);
        Duration::fromFormat($value, DurationFormat::Compact);
    }

    /**
     * @return array<non-empty-string, array{0: string}>
     */
    public static function invalidCompactNotation(): array
    {
        return [
            'empty string' => [''],
            'wrong order' => ['3h 1w'],
            'duplicate unit' => ['1w 2w'],
            'clock format forbidden' => ['12:34:56'],
            'partial clock forbidden' => ['12:34'],
            'unknown unit' => ['10x'],
            'letters only' => ['abc'],
            'missing number' => ['h 10m'],
        ];
    }

    public function testCountOfReturnsWholeOccurrences(): void
    {
        $duration = Duration::of(hours: 5);
        $other = Duration::of(hours: 2);
        $result = $duration->divideInto($other);

        self::assertSame(2, $result->quotient);
        self::assertTrue($result->remainder->equals(Duration::of(hours: 1)));
        [$quotient, $remainder] = $result;
        self::assertSame($quotient, $result->quotient);
        self::assertInstanceOf(Duration::class, $remainder);
        self::assertTrue($result->remainder->equals($remainder));
    }

    public function testDividedIntoThrowsOnInvalidOffset(): void
    {
        $duration = Duration::of(hours: 5);
        $other = Duration::of(hours: 2);
        $result = $duration->divideInto($other);

        self::assertFalse(isset($result[2]));

        $this->expectException(ValueError::class);
        $result[2]; /* @phpstan-ignore-line */
    }

    public function testCountOfReturnsZeroWhenDurationIsSmaller(): void
    {
        $duration = Duration::of(minutes: 30);
        $other = Duration::of(hours: 1);
        $result = $duration->divideInto($other);

        self::assertSame(0, $result->quotient);
        self::assertTrue($result->remainder->equals($duration));
    }

    public function testCountOfHandlesExactDivision(): void
    {
        $duration = Duration::of(hours: 6);
        $other = Duration::of(hours: 2);
        $result = $duration->divideInto($other);

        self::assertSame(3, $result->quotient);
        self::assertTrue($result->remainder->isZero());
    }

    public function testCountOfThrowsWhenDividingByZeroDuration(): void
    {
        $this->expectException(DivisionByZeroError::class);
        $this->expectExceptionMessageIsOrContains('Cannot divide by zero duration.');

        Duration::of(hours: 1)->divideInto(Duration::zero());
    }

    public function testRemainderReturnsRemainingDuration(): void
    {
        $duration = Duration::of(hours: 5);
        $other = Duration::of(hours: 2);
        $result = $duration->divideInto($other);

        self::assertSame(2, $result->quotient);
        self::assertEquals(Duration::of(hours: 1), $result->remainder);
    }

    public function testRemainderReturnsZeroForExactDivision(): void
    {
        $duration = Duration::of(hours: 6);
        $other = Duration::of(hours: 2);
        $result = $duration->divideInto($other);

        self::assertSame(3, $result->quotient);
        self::assertTrue($result->remainder->isZero());
    }

    public function testRemainderReturnsOriginalDurationWhenSmaller(): void
    {
        $duration = Duration::of(minutes: 30);
        $other = Duration::of(hours: 1);
        $result = $duration->divideInto($other);

        self::assertSame(0, $result->quotient);
        self::assertEquals(Duration::of(minutes: 30), $result->remainder);
    }

    public function testRemainderThrowsWhenDividingByZeroDuration(): void
    {
        $this->expectException(DivisionByZeroError::class);
        $this->expectExceptionMessageIsOrContains('Cannot divide by zero duration.');

        Duration::of(hours: 1)->divideInto(Duration::zero());
    }

    public function testCountOfAndRemainderRespectDivisionIdentity(): void
    {
        $duration = Duration::of(hours: 5);
        $other = Duration::of(hours: 2)->negate();
        $result = $duration->divideInto($other);

        self::assertTrue(
            $duration->equals(
                $other->multiplyBy($result->quotient)->add($result->remainder)
            )
        );
    }

    public function testDurationHandlesConvertingToMilliseconds(): void
    {
        $compact = '1h2ms';

        self::assertSame(
            $compact,
            Duration::fromFormat($compact, DurationFormat::Compact)
                ->format(DurationFormat::Compact)
        );
    }

    public function test_it_creates_a_zero_duration(): void
    {
        $native = TimeDuration::fromSeconds(0);
        $duration = Duration::fromNative($native);

        self::assertSame(0, $duration->nanoseconds);
        self::assertTrue($duration->isZero());
    }

    public function test_it_creates_a_positive_duration(): void
    {
        $native = TimeDuration::fromSeconds(42, 123_456_000);
        $duration = Duration::fromNative($native);

        self::assertSame(42, $duration->seconds);
        self::assertSame(123_456_000, $duration->nanoseconds);
        self::assertFalse($duration->negative);
        self::assertFalse($duration->isZero());
    }

    public function test_it_creates_a_negative_duration(): void
    {
        $native = TimeDuration::fromSeconds(42, 123_456_000)->negate();
        $duration = Duration::fromNative($native);

        self::assertSame(42, $duration->seconds);
        self::assertSame(123_456_000, $duration->nanoseconds);
        self::assertTrue($duration->negative);
        self::assertFalse($duration->isZero());
    }

    public function test_it_truncates_sub_microsecond_precision(): void
    {
        $native = TimeDuration::fromSeconds(1, 123_456_789);
        $duration = Duration::fromNative($native);

        self::assertSame(1, $duration->seconds);
        self::assertSame(123_456_789, $duration->nanoseconds);
    }

    public function test_it_accepts_the_maximum_supported_duration(): void
    {
        $native = TimeDuration::fromSeconds(9_223_372_035, 999_999_999);
        $duration = Duration::fromNative($native);

        self::assertSame(9_223_372_035, $duration->seconds);
        self::assertSame(999_999_999, $duration->nanoseconds);
        self::assertFalse($duration->negative);
        self::assertFalse($duration->isZero());
    }

    public function test_it_accepts_the_minimum_supported_duration(): void
    {
        $native = TimeDuration::fromSeconds(9_223_372_035, 999_999_999)->negate();
        $duration = Duration::fromNative($native);

        self::assertSame(9_223_372_035, $duration->seconds);
        self::assertSame(999_999_999, $duration->nanoseconds);
        self::assertTrue($duration->negative);
    }

    public function test_it_truncates_nanoseconds_to_the_nearest_lower_microsecond(): void
    {
        self::assertEquals(
            Duration::of(microseconds: 123_456),
            Duration::fromFormat('PT0.123456000S', DurationFormat::Iso8601)
        );
    }

    public function test_it_preserves_exact_microsecond_precision(): void
    {
        self::assertTrue(
            Duration::of(microseconds: 123_456)->equals(
                Duration::fromFormat('PT0.123456000S', DurationFormat::Iso8601)
            )
        );
    }

    public function test_it_preserves_microseconds_when_nanoseconds_are_a_multiple_of_one_thousand(): void
    {
        self::assertTrue(
            Duration::of(microseconds: 123_456)->equals(
                Duration::fromFormat('PT0.123456000S', DurationFormat::Iso8601)
            ),
        );
    }

    public function test_it_truncates_nanoseconds_after_whole_seconds(): void
    {
        self::assertTrue(
            Duration::of(seconds: 1, microseconds: 123_456)->equals(
                Duration::fromFormat('PT1.123456000S', DurationFormat::Iso8601)
            )
        );
    }

    public function test_it_truncates_negative_nanoseconds_after_whole_seconds(): void
    {
        self::assertTrue(
            Duration::of(seconds: 1, microseconds: 123_456, nanoseconds: 789)->negate()->equals(
                Duration::fromFormat('-PT1.123456789S', DurationFormat::Iso8601)
            )
        );
    }

    public function test_it_truncates_sub_microsecond_precision_in_compact_format(): void
    {
        self::assertTrue(
            Duration::of(nanoseconds: 9)->equals(
                Duration::fromFormat('9ns', DurationFormat::Compact)
            )
        );
    }

    public function test_it_truncates_nanoseconds_to_microseconds_in_compact_format(): void
    {
        self::assertTrue(
            Duration::of(hours: 1, microseconds: 28, nanoseconds: 9)->equals(
                Duration::fromFormat('1h28009ns', DurationFormat::Compact)
            )
        );
    }

    public function test_it_preserves_microseconds_in_compact_format(): void
    {
        self::assertTrue(
            Duration::of(hours: 1, microseconds: 28)->equals(
                Duration::fromFormat('1h28us', DurationFormat::Compact)
            )
        );
    }

    public function test_it_preserves_exact_microsecond_precision_in_compact_format(): void
    {
        self::assertTrue(
            Duration::of(hours: 1, microseconds: 280)->equals(
                Duration::fromFormat('1h280000ns', DurationFormat::Compact)
            )
        );
    }

    public function test_it_renders_the_duration_as_a_number(): void
    {
        $duration = Duration::of(hours: 23, seconds: 3);
        self::assertSame("1380.05", $duration->toNumberString(Unit::Minute, 2));
        self::assertSame("1380", $duration->toNumberString(Unit::Minute));

        $this->expectException(ValueError::class);
        $duration->toNumberString(Unit::Minute, -5);
    }

    #[DataProvider('quantityProvider')]
    public function test_format_quantity(Duration $duration, string $expected): void
    {
        self::assertSame($expected, $duration->format(DurationFormat::LargestUnit));
    }

    /**
     * @return iterable<non-empty-string, array{0: Duration, 1: non-empty-string}>
     */
    public static function quantityProvider(): iterable
    {
        yield 'nanoseconds' => [
            Duration::of(nanoseconds: 123),
            '123ns',
        ];

        yield 'microseconds' => [
            Duration::of(microseconds: 123),
            '123µs',
        ];

        yield 'fractional microseconds' => [
            Duration::of(microseconds: 123, nanoseconds: 456),
            '123.456µs',
        ];

        yield 'milliseconds' => [
            Duration::of(milliseconds: 123),
            '123ms',
        ];

        yield 'fractional milliseconds' => [
            Duration::of(milliseconds: 123, microseconds: 456),
            '123.456ms',
        ];

        yield 'seconds' => [
            Duration::of(seconds: 42),
            '42s',
        ];

        yield 'fractional seconds' => [
            Duration::of(seconds: 42, milliseconds: 123),
            '42.123s',
        ];

        yield 'minutes' => [
            Duration::of(minutes: 10),
            '10m',
        ];

        yield 'fractional minutes' => [
            Duration::of(minutes: 10, seconds: 30),
            '10.5m',
        ];

        yield 'hours' => [
            Duration::of(hours: 3),
            '3h',
        ];

        yield 'fractional hours' => [
            Duration::of(hours: 3, minutes: 30),
            '3.5h',
        ];

        yield 'days' => [
            Duration::of(days: 21),
            '3w',
        ];

        yield 'fractional days' => [
            Duration::of(days: 21, hours: 13, minutes: 55, seconds: 12)->negate(),
            '-21.58d',
        ];

        yield 'weeks' => [
            Duration::of(weeks: 2),
            '2w',
        ];

        yield 'fractional weeks' => [
            Duration::of(weeks: 2, days: 3),
            '17d',
        ];
    }

    #[DataProvider('singleUnitProvider')]
    public function testSingleUnitFormat(
        Duration $duration,
        string $expected,
    ): void {
        self::assertSame(
            $expected,
            $duration->format(DurationFormat::LargestUnit),
        );
    }

    public static function singleUnitProvider(): iterable
    {
        yield 'exact hours' => [
            Duration::of(hours: 2),
            '2h',
        ];

        yield 'fractional hours' => [
            Duration::of(minutes: 90),
            '1.5h',
        ];

        yield 'fractional seconds' => [
            Duration::of(seconds: 1, milliseconds: 250),
            '1.25s',
        ];

        yield 'fractional milliseconds' => [
            Duration::of(milliseconds: 1, microseconds: 250),
            '1.25ms',
        ];

        yield 'negative fractional hours' => [
            Duration::of(minutes: 90)->negate(),
            '-1.5h',
        ];

        yield 'negative fractional seconds' => [
            Duration::of(seconds: 1, milliseconds: 250)->negate(),
            '-1.25s',
        ];
    }

    #[DataProvider('totalUnitProvider')]
    public function testTotalUnitFormat(
        Duration $duration,
        string $expected,
    ): void {
        self::assertSame(
            $expected,
            $duration->format(DurationFormat::TotalUnit),
        );
    }

    /**
     * @return iterable<non-empty-string, array{0: Duration, 1: non-empty-string}>
     */
    public static function totalUnitProvider(): iterable
    {
        yield 'exact hours' => [
            Duration::of(hours: 2),
            '2h',
        ];

        yield 'total minutes' => [
            Duration::of(minutes: 90),
            '90m',
        ];

        yield 'total seconds' => [
            Duration::of(seconds: 90),
            '90s',
        ];

        yield 'total milliseconds' => [
            Duration::of(seconds: 1, milliseconds: 250),
            '1250ms',
        ];

        yield 'total microseconds' => [
            Duration::of(milliseconds: 1, microseconds: 250),
            '1250µs',
        ];

        yield 'negative total minutes' => [
            Duration::of(minutes: 90)->negate(),
            '-90m',
        ];

        yield 'negative total milliseconds' => [
            Duration::of(seconds: 1, milliseconds: 250)->negate(),
            '-1250ms',
        ];
    }

    #[DataProvider('singleUnitParsingProvider')]
    public function testSingleUnitParsing(
        string $value,
        Duration $expected,
    ): void {
        self::assertTrue(Duration::fromFormat($value, DurationFormat::LargestUnit)->equals($expected));

    }

    /**
     * @return iterable<non-empty-string, array{0: non-empty-string, 1: Duration}>
     */
    public static function singleUnitParsingProvider(): iterable
    {
        yield 'integer' => [
            '2h',
            Duration::of(hours: 2),
        ];

        yield 'fractional hours' => [
            '1.5h',
            Duration::of(minutes: 90),
        ];

        yield 'fractional seconds' => [
            '1.25s',
            Duration::of(seconds: 1, milliseconds: 250),
        ];

        yield 'negative fractional hours' => [
            '-1.5h',
            Duration::of(minutes: 90)->negate(),
        ];

        yield 'negative fractional seconds' => [
            '-1.25s',
            Duration::of(seconds: 1, milliseconds: 250)->negate(),
        ];
    }

    #[DataProvider('totalUnitParsingProvider')]
    public function testTotalUnitParsing(
        string $value,
        Duration $expected,
    ): void {
        self::assertTrue(Duration::fromFormat($value, DurationFormat::TotalUnit)->equals($expected));
    }

    /**
     * @return iterable<non-empty-string, array{0: non-empty-string, 1: Duration}>
     */
    public static function totalUnitParsingProvider(): iterable
    {
        yield 'hours' => [
            '2h',
            Duration::of(hours: 2),
        ];

        yield 'total minutes' => [
            '90m',
            Duration::of(minutes: 90),
        ];

        yield 'total milliseconds' => [
            '1250ms',
            Duration::of(seconds: 1, milliseconds: 250),
        ];

        yield 'negative total minutes' => [
            '-90m',
            Duration::of(minutes: 90)->negate(),
        ];

        yield 'negative total milliseconds' => [
            '-1250ms',
            Duration::of(seconds: 1, milliseconds: 250)->negate(),
        ];
    }
}