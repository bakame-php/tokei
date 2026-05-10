<?php

declare(strict_types=1);

namespace Bakame\Tokei;

use DateTime;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[CoversClass(InvalidTime::class)]
#[CoversClass(Time::class)]
final class TimeTest extends TestCase
{
    /* -------------------------------------------------
     * Creation
     * ------------------------------------------------- */

    public function testFromPartsCreatesCorrectTime(): void
    {
        $time = Time::at(10, 15, 30, 123456);

        self::assertSame(10, $time->hour);
        self::assertSame(15, $time->minute);
        self::assertSame(30, $time->second);
        self::assertSame(123456, $time->microsecond);
    }

    #[TestWith(['part' => 25], 'the hour component is too high')]
    #[TestWith(['part' => -1], 'the hour component is too low')]
    public function testDomainRejectsInvalidHours(int $part): void
    {
        $this->expectExceptionObject(InvalidTime::dueToMalformedHour($part));

        Time::at($part);
    }

    #[TestWith(['part' => 60], 'the minute component is too high')]
    #[TestWith(['part' => -1], 'the minute component is too low')]
    public function testDomainRejectsInvalidMinutes(int $part): void
    {
        $this->expectExceptionObject(InvalidTime::dueToMalformedMinute($part));

        Time::at(hour: 0, minute: $part);
    }

    #[TestWith(['part' => 60], 'the second component is too high')]
    #[TestWith(['part' => -1], 'the second component is too low')]
    public function testDomainRejectsInvalidSeconds(int $part): void
    {
        $this->expectExceptionObject(InvalidTime::dueToMalformedSecond($part));

        Time::at(hour: 0, second: $part);
    }

    #[TestWith(['part' => 1_000_001], 'the microsecond component is too high')]
    #[TestWith(['part' => -1], 'the microsecond component is too low')]
    public function testDomainRejectsInvalidMicroseconds(int $part): void
    {
        $this->expectExceptionObject(InvalidTime::dueToMalformedMicrosecond($part));

        Time::at(hour: 0, microsecond: $part);
    }

    public function testMidnightAndNoon(): void
    {
        self::assertSame(0, Time::midnight()->hour);
        self::assertSame(12, Time::noon()->hour);
        self::assertSame(23, Time::max()->hour);
    }

    /* -------------------------------------------------
     * From microseconds
     * ------------------------------------------------- */

    public function testFromMicrosecondsWrapsCorrectly(): void
    {
        $time = Time::atMicroOfDay(25 * 3_600_500_000);
        self::assertSame('01:00:12.500000', $time->format(format: SubSecondDisplay::Always));

        $time = Time::atMilliOfDay(25 * 3_600_500);
        self::assertSame('01:00:12.500000', $time->format(format: SubSecondDisplay::Always));

        $time = Time::atSecondOfDay(25 * 3_600);
        self::assertSame('01:00:00.000000', $time->format(format: SubSecondDisplay::Always));

        $time = Time::atMinuteOfDay(25 * 60);
        self::assertSame('01:00:00.000000', $time->format(format: SubSecondDisplay::Always));
    }

    /* -------------------------------------------------
     * Parsing
     * ------------------------------------------------- */

    public function testParseString(): void
    {
        $time = Time::parse('12:34:56.123456');

        self::assertNotNull($time);
        self::assertSame(12, $time->hour);
        self::assertSame(34, $time->minute);
        self::assertSame(56, $time->second);
        self::assertSame(123456, $time->microsecond);
    }

    public function testParseWithoutSeconds(): void
    {

        $time = Time::parse('08:15');

        self::assertInstanceOf(Time::class, $time);
        self::assertSame(8, $time->hour);
        self::assertSame(15, $time->minute);
        self::assertSame(0, $time->second);
    }

    public function testParseInvalidReturnsNull(): void
    {
        self::assertNull(Time::parse('99:99:99'));
    }

    public function testParseDateTime(): void
    {
        $dt = new DateTimeImmutable('2024-01-01 10:20:30.123456');
        $time = Time::extractFrom($dt);

        self::assertSame(10, $time->hour);
        self::assertSame(20, $time->minute);
        self::assertSame(30, $time->second);
        self::assertSame(123456, $time->microsecond);
    }

    #[TestWith(['separator' => '@1'], 'the separator can not contain more than 1 byte length character')]
    #[TestWith(['separator' => '3'], 'the separator can not be a digit')]
    public function testInvalidSeparatorThrows(string $separator): void
    {
        $this->expectExceptionObject(InvalidTime::dueToInvalidSeparator($separator));

        Time::parse('12-34-56', $separator);
    }

    public function testParseReturnsNullOnFailure(): void
    {
        self::assertNull(Time::parse('12-12-douze', '-'));
    }

    /* -------------------------------------------------
     * Formatting
     * ------------------------------------------------- */

    public function testFormatDefault(): void
    {
        $time = Time::at(9, 5, 3);

        self::assertSame('09:05:03', $time->format());
    }

    public function testFormatPadded(): void
    {
        $time = Time::at(9, 5, 3);

        self::assertSame('09:05:03', $time->format());
    }

    public function testFormatWithMicroseconds(): void
    {
        $time = Time::at(1, 2, 3, 45);

        self::assertSame('1:2:3.000045', $time->format(':', PaddingMode::Unpadded, SubSecondDisplay::Always));
    }

    public function testFormatAutoMicroseconds(): void
    {
        $time = Time::at(1, 2, 3);

        self::assertSame('1:2:3', $time->format(':', PaddingMode::Unpadded));
    }

    /* -------------------------------------------------
     * Arithmetic
     * ------------------------------------------------- */

    public function testAddTime(): void
    {
        $time = Time::at(10)
            ->add(Duration::of(2, 30, 15, 500));

        self::assertSame(12, $time->hour);
        self::assertSame(30, $time->minute);
        self::assertSame(15, $time->second);
        self::assertSame(500, $time->microsecond);
    }

    public function testAddTimeWrapsDay(): void
    {
        $time = Time::at(23)->add(Duration::of(hours: 2));

        self::assertSame(1, $time->hour);
    }

    public function testAddWithoutArgumentChangesNothing(): void
    {
        $time = Time::at(23);

        self::assertSame($time, $time->add(Duration::of()));
    }

    /* -------------------------------------------------
     * Comparison
     * ------------------------------------------------- */

    public function testComparison(): void
    {
        $a = Time::at(10);
        $b = Time::at(12);

        self::assertTrue($a->isBefore($b));
        self::assertTrue($a->isBeforeOrEqual($b));
        self::assertTrue($b->isAfter($a));
        self::assertTrue($b->isAfterOrEqual($a));
        self::assertTrue($a->equals($a));
        self::assertTrue($a->isBeforeOrEqual($a));
        self::assertTrue($a->isAfterOrEqual($a));
    }

    /* -------------------------------------------------
     * Diff
     * ------------------------------------------------- */

    public function testDiffSigned(): void
    {
        $a = Time::at(8);
        $b = Time::at(10);

        self::assertSame(2 * 3_600_000_000, $a->diff($b)->toMicro());
    }

    public function testDiffForwardWraps(): void
    {
        $a = Time::at(23);
        $b = Time::at(1);

        $diff = $a->distance($b);

        self::assertSame(2 * 3_600_000_000, $diff->toMicro());
    }

    /* -------------------------------------------------
     * Edge cases
     * ------------------------------------------------- */

    public function testZeroAddReturnsSameInstance(): void
    {
        $time = Time::at(10);

        self::assertSame($time, $time->add(Duration::zero()));
    }

    public function testMicroseconds(): void
    {
        self::assertSame(10 * 3_600_000_000, Time::at(10)->toMicroOfDay());
    }

    public function test_apply_to_datetime_immutable(): void
    {
        $date = new DateTimeImmutable('2026-05-11 08:15:30', new DateTimeZone('Africa/Luanda'));
        $time = Time::at(14, 45, 12, 123456);
        $result = $time->applyTo($date);

        self::assertSame('2026-05-11 14:45:12.123456', $result->format('Y-m-d H:i:s.u'));
        self::assertSame('Africa/Luanda', $result->getTimezone()->getName());

        // Original instance remains unchanged
        self::assertSame('2026-05-11 08:15:30.000000', $date->format('Y-m-d H:i:s.u'));
    }

    public function test_apply_to_mutable_datetime_returns_immutable(): void
    {
        $date = new DateTime('2026-05-11 08:15:30', new DateTimeZone('UTC'));
        $time = Time::at(22, 1, 2, 999999);

        self::assertSame('2026-05-11 22:01:02.999999', $time->applyTo($date)->format('Y-m-d H:i:s.u'));

        // Original mutable DateTime is NOT modified
        self::assertSame('2026-05-11 08:15:30.000000', $date->format('Y-m-d H:i:s.u'));
    }

    public function test_apply_to_preserves_date(): void
    {
        $date = new DateTimeImmutable('2030-12-25 00:00:00');
        $time = Time::at(9, 30);

        self::assertSame('2030-12-25 09:30:00', $time->applyTo($date)->format('Y-m-d H:i:s'));
    }

    public function test_apply_to_preserves_timezone(): void
    {
        $timezone = new DateTimeZone('Asia/Tokyo');
        $date = new DateTimeImmutable('2026-01-01 00:00:00', $timezone);

        self::assertSame('Asia/Tokyo', Time::at(12)->applyTo($date)->getTimezone()->getName());
    }

    /**
     * @param array<non-empty-string, int> $arguments
     */
    #[DataProvider('withProvider')]
    public function test_with_updates_selected_components(
        Time $original,
        array $arguments,
        string $expected,
    ): void {
        self::assertSame($expected, $original->with(...$arguments)->format());
    }

    /**
     * @throws InvalidTime
     *
     * @return iterable<non-empty-string, array{
     *     0:Time,
     *     1: array<non-empty-string, int>,
     *     2: non-empty-string
     * }>
     */
    public static function withProvider(): iterable
    {
        $base = Time::at(23, 54, 23, 123456);

        yield 'replace hour' => [
            $base,
            ['hour' => 8],
            '08:54:23.123456',
        ];

        yield 'replace minute' => [
            $base,
            ['minute' => 12],
            '23:12:23.123456',
        ];

        yield 'replace second' => [
            $base,
            ['second' => 5],
            '23:54:05.123456',
        ];

        yield 'replace microsecond' => [
            $base,
            ['microsecond' => 999],
            '23:54:23.000999',
        ];

        yield 'replace multiple components' => [
            $base,
            [
                'hour' => 8,
                'minute' => 15,
            ],
            '08:15:23.123456',
        ];

        yield 'replace all components' => [
            $base,
            [
                'hour' => 1,
                'minute' => 2,
                'second' => 3,
                'microsecond' => 4,
            ],
            '01:02:03.000004',
        ];
    }

    public function test_with_preserves_original_instance(): void
    {
        $original = Time::at(23, 54, 23);

        $updated = $original->with(hour: 8);

        self::assertSame('23:54:23', $original->format());
        self::assertSame('08:54:23', $updated->format());
    }

    public function test_with_returns_same_instance_when_no_change(): void
    {
        $time = Time::at(23, 54, 23, 123456);

        self::assertSame($time, $time->with());
    }

    public function test_with_throws_on_invalid_hour(): void
    {
        $time = Time::at(23, 54, 23);

        $this->expectException(InvalidTime::class);

        $time->with(hour: 24);
    }

    public function test_with_throws_on_invalid_minute(): void
    {
        $time = Time::at(23, 54, 23);

        $this->expectException(InvalidTime::class);

        $time->with(minute: 60);
    }

    public function test_with_throws_on_invalid_second(): void
    {
        $time = Time::at(23, 54, 23);

        $this->expectException(InvalidTime::class);

        $time->with(second: 60);
    }

    public function test_with_throws_on_invalid_microsecond(): void
    {
        $time = Time::at(23, 54, 23);

        $this->expectException(InvalidTime::class);

        $time->with(microsecond: 1_000_000);
    }
}
