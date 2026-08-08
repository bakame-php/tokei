<?php

declare(strict_types=1);

namespace Tests;

use Bakame\Tokei\Duration;
use Bakame\Tokei\DurationFormatter;
use Bakame\Tokei\Internal\DurationParts;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhp;
use PHPUnit\Framework\TestCase;

#[CoversClass(DurationFormatter::class)]
#[CoversClass(DurationParts::class)]
final class DurationFormatterTest extends TestCase
{
    public function testZeroDuration(): void
    {
        $formatter = new DurationFormatter('en');

        self::assertSame('0 seconds', $formatter->format(Duration::zero()));
    }

    public function testSingleUnit(): void
    {
        $formatter = new DurationFormatter('en');

        self::assertSame('5 days', $formatter->format(Duration::of(days: 5)));
    }

    public function testSingularUnit(): void
    {
        $formatter = new DurationFormatter('en');

        self::assertSame('1 hour', $formatter->format(Duration::of(hours: 1)));
    }

    public function testMultipleUnits(): void
    {
        $formatter = new DurationFormatter('en');

        self::assertSame(
            '5 days, 3 seconds, and 30 milliseconds',
            $formatter->format(Duration::of(days: 5, seconds: 3, milliseconds: 30,)),
        );
    }

    public function testNegativeDurationIsFormattedAsAbsoluteValue(): void
    {
        $formatter = new DurationFormatter();

        self::assertSame(
            '5 days, 3 seconds, and 30 milliseconds',
            $formatter->format(Duration::of(days: 5, seconds: 3, milliseconds: 30)->negate()),
        );
    }

    /**
     * @param non-empty-string $expected
     */
    #[DataProvider('unitProvider')]
    public function testUnits(string $expected, Duration $duration,): void
    {

        self::assertSame($expected, new DurationFormatter()->format($duration));
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
        $formatter = new DurationFormatter('fr_FR');

        self::assertSame(
            '5 jours, 3 secondes et 30 millisecondes',
            $formatter->format(Duration::of(days: 5, seconds: 3, milliseconds: 30,)),
        );
    }
}