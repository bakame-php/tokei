<?php

declare(strict_types=1);

namespace Bakame\Tokei;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

use const PHP_INT_MIN;

#[CoversClass(InvalidDuration::class)]
#[CoversClass(Duration::class)]
final class DurationTest extends TestCase
{
    public function testParseMicroseconds(): void
    {
        $duration = Duration::of(2, 15, 42, 123_456);

        self::assertSame(2, $duration->hours);
        self::assertSame(15, $duration->minutes);
        self::assertSame(42, $duration->seconds);
        self::assertSame(123_456, $duration->microseconds);
        self::assertFalse($duration->inverted);
        self::assertSame(8_142_123_456, $duration->toMicro());
    }

    public function testParseNegativeMicroseconds(): void
    {
        $duration = Duration::of(microseconds: -1_500_000);

        self::assertSame(0, $duration->hours);
        self::assertSame(0, $duration->minutes);
        self::assertSame(1, $duration->seconds);
        self::assertSame(500_000, $duration->microseconds);
        self::assertTrue($duration->inverted);
    }

    public function testFormatMicrosecondsWithoutFraction(): void
    {
        self::assertSame('9:25:00', Duration::of(9, 25)->toClockFormat());
    }

    public function testFormatMicrosecondsWithFraction(): void
    {
        self::assertSame('1:02:03.000045', Duration::of(1, 2, 3, 45)->toClockFormat());
    }

    public function testFormatNegativeMicroseconds(): void
    {
        self::assertSame('-4:05:06', Duration::of(4, 5, 6)->negate()->toClockFormat());
    }

    public function testMicrosecondsToDateInterval(): void
    {
        $interval = Duration::of(27, 12, 5, 123_456)->toDateInterval();

        self::assertSame(1, $interval->d);
        self::assertSame(3, $interval->h);
        self::assertSame(12, $interval->i);
        self::assertSame(5, $interval->s);
        self::assertSame(0, $interval->invert);

        self::assertEqualsWithDelta(0.123456, $interval->f, 0.000001);
    }

    public function testNegativeMicrosecondsToDateInterval(): void
    {
        $interval = (Duration::of(microseconds:-5_000_000))->toDateInterval();

        self::assertSame(1, $interval->invert);
        self::assertSame(5, $interval->s);
    }

    public function testZeroMicroseconds(): void
    {
        $duration = Duration::of(microseconds:0);

        self::assertSame('0:00:00', $duration->toClockFormat());

        self::assertSame(0, $duration->hours);
        self::assertSame(0, $duration->minutes);
        self::assertSame(0, $duration->seconds);
        self::assertSame(0, $duration->microseconds);
        self::assertFalse($duration->inverted);
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

        self::assertSame('1:30:00', $a->sum($b)->toClockFormat());
    }

    public function test_add_multiple_durations(): void
    {
        $base = Duration::of(hours: 1);
        $result = $base->sum(
            Duration::of(minutes: 30),
            Duration::of(seconds: 45),
            Duration::of(microseconds: 123456),
        );

        self::assertSame('1:30:45.123456', $result->toClockFormat());
    }

    public function test_add_negative_duration(): void
    {
        $a = Duration::of(hours: 5);
        $b = Duration::of(hours: -2);

        self::assertSame('3:00:00', $a->sum($b)->toClockFormat());
    }

    public function test_add_result_can_be_negative(): void
    {
        $a = Duration::of(hours: 1);
        $b = Duration::of(hours: -3);

        self::assertSame('-2:00:00', $a->sum($b)->toClockFormat());
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

        self::assertSame('0:00:00.750000', $a->sum($b)->toClockFormat());
    }

    public function test_abs_negate(): void
    {
        $duration = Duration::of(microseconds: -500000);

        self::assertEquals($duration, $duration->abs()->negate());
    }

    #[DataProvider('iso8601Provider')]
    public function test_to_iso8601(int $microseconds, string $expected): void
    {
        self::assertSame($expected, Duration::of(microseconds:$microseconds)->toIso8601());
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
        Precision $precision,
        int $expectedMicroseconds,
    ): void {
        self::assertSame(
            $expectedMicroseconds,
            Duration::of(microseconds:$microseconds)
                ->truncateTo($precision)
                ->toMicro(),
        );
    }

    /**
     * @return iterable<non-empty-string, array{0: int, 1: Precision, 2: int}>
     */
    public static function truncateProvider(): iterable
    {
        // 1h 1m 1s + 500ms = 3_661_500_000 µs
        yield 'truncate to seconds removes microseconds' => [
            3_661_500_000,
            Precision::Seconds,
            3_661_000_000,
        ];

        yield 'truncate to minutes removes seconds and microseconds' => [
            3_661_500_000,
            Precision::Minutes,
            3_660_000_000,
        ];

        yield 'truncate to hours removes minutes seconds and microseconds' => [
            3_661_500_000,
            Precision::Hours,
            3_600_000_000,
        ];

        yield 'zero duration stays zero' => [
            0,
            Precision::Seconds,
            0,
        ];

        yield 'already clean seconds unchanged' => [
            1_000_000,
            Precision::Seconds,
            1_000_000,
        ];

        yield 'negative duration is preserved when inverted' => [
            -3_661_500_000,
            Precision::Minutes,
            -3_660_000_000,
        ];
    }

    #[DataProvider('truncateImmutabilityProvider')]
    public function test_truncate_is_immutable(
        int $microseconds,
        Precision $precision,
    ): void {
        $duration = Duration::of(microseconds:$microseconds);

        $result = $duration->truncateTo($precision);

        self::assertNotSame($duration, $result);
    }

    /**
     * @return iterable<array{0: int, 1: Precision}>
     */
    public static function truncateImmutabilityProvider(): iterable
    {
        yield [3_661_500_000, Precision::Seconds];
        yield [3_661_500_000, Precision::Minutes];
        yield [-3_661_500_000, Precision::Hours];
    }

    public function test_truncate_preserves_sign_consistency(): void
    {
        $positive = Duration::of(microseconds:3_661_500_000);
        $negative = Duration::of(microseconds:-3_661_500_000);

        self::assertTrue($positive->truncateTo(Precision::Minutes)->toMicro() > 0);
        self::assertTrue($negative->truncateTo(Precision::Minutes)->toMicro() < 0);
    }

    #[TestWith([PHP_INT_MIN], 'overflow with lower bound integer')]
    #[TestWith([PHP_INT_MAX], 'overflow with upper bound integer')]
    public function test_it_can_not_invert_all_values(int $bound): void
    {
        $this->expectException(InvalidDuration::class);
        $this->expectExceptionMessage('The duration exceeds the supported range.');

        Duration::of(microseconds:$bound)->negate();
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

        self::assertSame($expected, $result->toClockFormat());
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

        self::assertSame('10:00:00', $duration->toClockFormat());
        self::assertSame('15:00:00', $modified->toClockFormat());
    }

    public function test_with_returns_same_instance_when_called_without_arguments(): void
    {
        $duration = Duration::of(hours: 1);

        self::assertSame($duration, $duration->increment());
    }

    public function testItParsesSimpleMinutes(): void
    {
        $duration = Duration::fromIso8601('PT30M');

        self::assertSame('PT30M', $duration->toIso8601());
    }

    public function testItParsesHoursMinutesSeconds(): void
    {
        $duration = Duration::fromIso8601('PT1H30M15S');

        self::assertSame('PT1H30M15S', $duration->toIso8601());
    }

    public function testItParsesFractionalSeconds(): void
    {
        $duration = Duration::fromIso8601('PT0.5S');

        self::assertSame('PT0.5S', $duration->toIso8601());
    }

    public function testItParsesDays(): void
    {
        $duration = Duration::fromIso8601('P2DT3H');

        self::assertSame('P2DT3H', $duration->toIso8601());
    }

    public function testItParsesNegativeDuration(): void
    {
        $duration = Duration::fromIso8601('-PT30S');

        self::assertSame('-PT30S', $duration->toIso8601());
    }

    public function testItParseAndNormalizeDuration(): void
    {
        $rawIso8601 = '-PT25H0.5S';
        $duration = Duration::fromIso8601($rawIso8601);

        self::assertNotSame($rawIso8601, $duration->toIso8601());
        self::assertSame('-P1DT1H0.5S', $duration->toIso8601());
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

        self::assertSame('P14D', $duration->toIso8601());
    }

    public function testItParsesWeeksAndDays(): void
    {
        $duration = Duration::fromIso8601('P1W2D');

        self::assertSame('P9D', $duration->toIso8601());
    }

    public function testItParsesNegativeWeeks(): void
    {
        $duration = Duration::fromIso8601('-P3W');

        self::assertSame('-P21D', $duration->toIso8601());
    }

    public function testItParsesWeeksWithTimeComponents(): void
    {
        $duration = Duration::fromIso8601('P1WT2H30M');

        self::assertSame('P7DT2H30M', $duration->toIso8601());
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
        self::assertSame('PT4H', Duration::of(hours: 2)->multipliedBy(2)->toIso8601());
        self::assertSame('PT4M', Duration::of(minutes: 2)->multipliedBy(2)->toIso8601());
    }
}
