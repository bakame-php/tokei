<?php

declare(strict_types=1);

namespace Bakame\Tokei\Tests;

use Bakame\Tokei\Duration;
use Bakame\Tokei\Internal\DurationParts;
use Bakame\Tokei\Localize;
use Bakame\Tokei\Time;
use Bakame\Tokei\TimeException;
use Bakame\Tokei\TimeVerbosity;
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
}