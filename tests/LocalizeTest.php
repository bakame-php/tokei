<?php

declare(strict_types=1);

namespace Bakame\Tokei\Tests;

use Bakame\Tokei\Duration;
use Bakame\Tokei\DurationStyle;
use Bakame\Tokei\Internal\DurationParts;
use Bakame\Tokei\Localize;
use Bakame\Tokei\Time;
use Bakame\Tokei\TimeException;
use Bakame\Tokei\TimeVerbosity;
use Bakame\Tokei\UnitWidth;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhp;
use PHPUnit\Framework\TestCase;
use ValueError;

#[CoversClass(Localize::class)]
#[CoversClass(DurationParts::class)]
final class LocalizeTest extends TestCase
{
    public function testZeroDuration(): void
    {
        self::assertSame('0 seconds', Localize::duration(Duration::zero(), 'en'));
    }

    public function testSingleUnit(): void
    {
        self::assertSame('5 days', Localize::duration(Duration::of(days: 5), 'en'));
    }

    public function testSingularUnit(): void
    {
        self::assertSame('1 hour', Localize::duration(Duration::of(hours: 1), 'en'));
    }

    public function testMultipleUnits(): void
    {
        self::assertSame(
            '5 days, 3 seconds, and 30 milliseconds',
            Localize::duration(Duration::of(days: 5, seconds: 3, milliseconds: 30), 'en'),
        );
    }

    public function testNegativeDurationIsFormattedAsAbsoluteValue(): void
    {
        self::assertSame(
            '5 days, 3 seconds, and 30 milliseconds',
            Localize::duration(Duration::of(days: 5, seconds: 3, milliseconds: 30)->negate(), 'en'),
        );
    }

    /**
     * @param non-empty-string $expected
     */
    #[DataProvider('unitProvider')]
    public function testUnits(string $expected, Duration $duration,): void
    {
        self::assertSame($expected, Localize::duration($duration, 'en'));
    }

    /**
     * @return iterable<string, array{string, Duration}>
     */
    public static function unitProvider(): iterable
    {
        yield 'week' => [
            '1 week',
            Duration::of(weeks: 1),
        ];

        yield 'day' => [
            '1 day',
            Duration::of(days: 1),
        ];

        yield 'hour' => [
            '1 hour',
            Duration::of(hours: 1),
        ];

        yield 'minute' => [
            '1 minute',
            Duration::of(minutes: 1),
        ];

        yield 'second' => [
            '1 second',
            Duration::of(seconds: 1),
        ];

        yield 'millisecond' => [
            '1 millisecond',
            Duration::of(milliseconds: 1),
        ];

        yield 'microsecond' => [
            '1 microsecond',
            Duration::of(microseconds: 1),
        ];

        yield 'nanosecond' => [
            '1 nanosecond',
            Duration::of(nanoseconds: 1),
        ];
    }

    #[RequiresPhp('>= 8.5.0')]
    public function testFrenchLocale(): void
    {
        self::assertSame(
            '5 jours, 3 secondes et 30 millisecondes',
            Localize::duration(Duration::of(days: 5, seconds: 3, milliseconds: 30), 'fr_FR'),
        );
    }

    public function testRejectsInvalidTimezone(): void
    {
        $this->expectException(TimeException::class);

        Localize::time(new DateTimeImmutable(),  locale: 'en_US', timezone: 'Mars/Phobos');
    }

    public function testRejectsInvalidLocale(): void
    {
        $this->expectException(ValueError::class);

        Localize::time(Time::utc(), 'foobar');
    }

    public function testFormatsTime(): void
    {
        $formatted = Localize::time(Time::at(hour: 14, minute: 30), 'en_US');

        self::assertStringContainsString('PM', $formatted);
    }

    public function testFormatsUsingFormatterTimezone(): void
    {
        $result = Localize::time(Time::at(hour: 10), locale: 'en_US', timezone: 'Europe/Brussels');

        self::assertStringContainsString('AM', $result);
    }

    public function testCanOverrideTimezone(): void
    {
        $noon = Time::at(hour: 14, minute: 30, second: 13);

        self::assertNotSame(
            Localize::time($noon, 'en','Europe/Brussels'),
            Localize::time($noon, 'fr', 'Asia/Tokyo'),
        );
    }

    public function testTimezoneOverrideDoesNotMutateFormatter(): void
    {
        $noon = Time::noon();
        $first = Localize::time($noon, 'tr_CY');
        $changedTimezone = Localize::time($noon,  'tr_CY','Asia/Tokyo', TimeVerbosity::Long);
        $second = Localize::time($noon, 'tr_CY','Europe/Brussels', TimeVerbosity::Long);

        self::assertNotSame($first, $second);
        self::assertNotSame($first, $changedTimezone);
    }

    public function test_with_preserves_original_instance(): void
    {
        $original = Time::at(23, 54, 23);
        $updated = $original->with(hour: 8);

        self::assertNotSame(
            Localize::time($updated, 'tr_CY', verbosity: TimeVerbosity::Short),
            Localize::time($updated, 'tr_CY', verbosity: TimeVerbosity::Long),
        );
    }

    public function testDefaultStyleIsDecomposed(): void
    {
        $duration = Duration::of(hours: 3, minutes: 12);

        self::assertSame(
            '3 hours and 12 minutes',
            Localize::duration(
                duration: $duration,
                locale: 'en',
            ),
        );
    }

    public function testDecomposedStyle(): void
    {
        $duration = Duration::of(hours: 3, minutes: 12);

        self::assertSame(
            '3 hours and 12 minutes',
            Localize::duration(duration: $duration, locale: 'en'),
        );
    }

    public function testSingleUnitStyle(): void
    {
        $duration = Duration::of(minutes: 192);

        self::assertSame(
            '3.2 hours',
            Localize::duration(
                duration: $duration,
                locale: 'en',
                style: DurationStyle::LargestUnit,
            ),
        );
    }

    public function testTotalUnitStyle(): void
    {
        $duration = Duration::of(minutes: 192);

        self::assertSame(
            '192 minutes',
            Localize::duration(
                duration: $duration,
                locale: 'en',
                style: DurationStyle::TotalUnit,
            ),
        );
    }

    public function testDecomposedStyleWithNarrowUnits(): void
    {
        $duration = Duration::of(hours: 3, minutes: 12);

        self::assertSame(
            '3h and 12m',
            Localize::duration(
                duration: $duration,
                locale: 'en',
                unitWidth: UnitWidth::Narrow,
            ),
        );
    }

    public function testSingleUnitStyleWithNarrowUnits(): void
    {
        $duration = Duration::of(minutes: 192);

        self::assertSame(
            '3.2h',
            Localize::duration(
                duration: $duration,
                locale: 'en',
                style: DurationStyle::LargestUnit,
                unitWidth: UnitWidth::Narrow,
            ),
        );
    }

    public function testTotalUnitStyleWithShortUnits(): void
    {
        $duration = Duration::of(minutes: 192);

        self::assertSame(
            '192 min',
            Localize::duration(
                duration: $duration,
                locale: 'en',
                style: DurationStyle::TotalUnit,
                unitWidth: UnitWidth::Short,
            ),
        );
    }

    public function testNegativeDurationDoesNotIncludeSign(): void
    {
        $duration = Duration::of(minutes: 192)->negate();

        self::assertSame(
            '3 hours and 12 minutes',
            Localize::duration(duration: $duration, locale: 'en'),
        );

        self::assertSame(
            '3.2 hours',
            Localize::duration(
                duration: $duration,
                locale: 'en',
                style: DurationStyle::LargestUnit,
            ),
        );

        self::assertSame(
            '192 minutes',
            Localize::duration(
                duration: $duration,
                locale: 'en',
                style: DurationStyle::TotalUnit,
            ),
        );
    }

    #[DataProvider('durationStyleProvider')]
    public function testDurationStyles(
        DurationStyle $style,
        string $expected,
    ): void {
        $duration = Duration::of(minutes: 192);

        self::assertSame(
            $expected,
            Localize::duration(
                duration: $duration,
                locale: 'en',
                style: $style,
            ),
        );
    }

    /**
     * @return iterable<non-empty-string, array{0: DurationStyle, 1: non-empty-string}>
     */
    public static function durationStyleProvider(): iterable
    {
        yield 'decomposed' => [
            DurationStyle::Decomposed,
            '3 hours and 12 minutes',
        ];

        yield 'single unit' => [
            DurationStyle::LargestUnit,
            '3.2 hours',
        ];

        yield 'total unit' => [
            DurationStyle::TotalUnit,
            '192 minutes',
        ];
    }

    #[DataProvider('durationStyleBoundaryProvider')]
    public function testDurationStyleBoundaries(
        Duration $duration,
        DurationStyle $style,
        string $expected,
    ): void {
        self::assertSame(
            $expected,
            Localize::duration(
                duration: $duration,
                locale: 'en',
                style: $style,
            ),
        );
    }

    /**
     * @return iterable<non-empty-string, array{0: Duration, 1: DurationStyle, 2: non-empty-string}>
     */
    public static function durationStyleBoundaryProvider(): iterable
    {
        yield 'exact hour - decomposed' => [
            Duration::of(minutes: 120),
            DurationStyle::Decomposed,
            '2 hours',
        ];

        yield 'exact hour - single unit' => [
            Duration::of(minutes: 120),
            DurationStyle::LargestUnit,
            '2 hours',
        ];

        yield 'exact hour - total unit' => [
            Duration::of(minutes: 120),
            DurationStyle::TotalUnit,
            '2 hours',
        ];

        yield 'one hour and one minute - decomposed' => [
            Duration::of(hours: 1, minutes: 1),
            DurationStyle::Decomposed,
            '1 hour and 1 minute',
        ];

        yield 'one hour and one minute - single unit' => [
            Duration::of(hours: 1, minutes: 1),
            DurationStyle::LargestUnit,
            '61 minutes',
        ];

        yield 'one hour and one minute - total unit' => [
            Duration::of(hours: 1, minutes: 1),
            DurationStyle::TotalUnit,
            '61 minutes',
        ];
    }

    #[DataProvider('subSecondDurationProvider')]
    public function testDecomposedStyleWithSubSecondDuration(Duration $duration, string $expected): void
    {
        self::assertSame($expected, Localize::duration(duration: $duration, locale: 'en'));
    }

    /**
     * @return iterable<non-empty-string, array{0: Duration, 1: non-empty-string}>
     */
    public static function subSecondDurationProvider(): iterable
    {
        yield 'milliseconds' => [
            Duration::of(milliseconds: 250),
            '250 milliseconds',
        ];

        yield 'microseconds' => [
            Duration::of(microseconds: 250),
            '250 microseconds',
        ];

        yield 'nanoseconds' => [
            Duration::of(nanoseconds: 250),
            '250 nanoseconds',
        ];

        yield 'seconds and milliseconds' => [
            Duration::of(seconds: 1, milliseconds: 250),
            '1 second and 250 milliseconds',
        ];

        yield 'seconds and microseconds' => [
            Duration::of(seconds: 1, microseconds: 250),
            '1 second and 250 microseconds',
        ];

        yield 'seconds and nanoseconds' => [
            Duration::of(seconds: 1, nanoseconds: 250),
            '1 second and 250 nanoseconds',
        ];
    }

    #[DataProvider('singleUnitSubSecondDurationProvider')]
    public function testSingleUnitStyleWithSubSecondDuration(
        Duration $duration,
        string $expected,
    ): void {
        self::assertSame(
            $expected,
            Localize::duration(
                duration: $duration,
                locale: 'en',
                style: DurationStyle::LargestUnit,
            ),
        );
    }

    /**
     * @return iterable<non-empty-string, array{0: Duration, 1: non-empty-string}>
     */
    public static function singleUnitSubSecondDurationProvider(): iterable
    {
        yield '250 milliseconds' => [
            Duration::of(milliseconds: 250),
            '250 milliseconds',
        ];

        yield '1.25 seconds' => [
            Duration::of(seconds: 1, milliseconds: 250),
            '1.25 seconds',
        ];

        yield '250 microseconds' => [
            Duration::of(microseconds: 250),
            '250 microseconds',
        ];

        yield '1.25 milliseconds' => [
            Duration::of(milliseconds: 1, microseconds: 250),
            '1.25 milliseconds',
        ];

        yield '250 nanoseconds' => [
            Duration::of(nanoseconds: 250),
            '250 nanoseconds',
        ];

        yield '1.25 microseconds' => [
            Duration::of(microseconds: 1, nanoseconds: 250),
            '1.25 microseconds',
        ];
    }

    #[DataProvider('totalUnitSubSecondDurationProvider')]
    public function testTotalUnitStyleWithSubSecondDuration(
        Duration $duration,
        string $expected,
    ): void {
        self::assertSame(
            $expected,
            Localize::duration(
                duration: $duration,
                locale: 'en',
                style: DurationStyle::TotalUnit,
            ),
        );
    }

    /**
     * @return iterable<non-empty-string, array{0: Duration, 1: non-empty-string}>
     */
    public static function totalUnitSubSecondDurationProvider(): iterable
    {
        yield '250 milliseconds' => [
            Duration::of(milliseconds: 250),
            '250 milliseconds',
        ];

        yield '1.25 seconds' => [
            Duration::of(seconds: 1, milliseconds: 250),
            '1,250 milliseconds',
        ];

        yield '250 microseconds' => [
            Duration::of(microseconds: 250),
            '250 microseconds',
        ];

        yield '1.25 milliseconds' => [
            Duration::of(milliseconds: 1, microseconds: 250),
            '1,250 microseconds',
        ];

        yield '250 nanoseconds' => [
            Duration::of(nanoseconds: 250),
            '250 nanoseconds',
        ];

        yield '1.25 microseconds' => [
            Duration::of(microseconds: 1, nanoseconds: 250),
            '1,250 nanoseconds',
        ];
    }
}