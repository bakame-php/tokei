<?php

declare(strict_types=1);

namespace Bakame\Tokei;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Throwable;

use function iterator_to_array;
use function json_encode;
use function serialize;
use function unserialize;

use const JSON_UNESCAPED_SLASHES;

#[CoversClass(IntervalSet::class)]
#[CoversClass(Interval::class)]
#[CoversClass(InvalidInterval::class)]
#[CoversClass(Duration::class)]
#[CoversClass(Time::class)]
final class IntervalTest extends TestCase
{
    /* -------------------------------------------------
     * Construction helpers
     * ------------------------------------------------- */

    public function test_after_creates_expected_range(): void
    {
        $range = Interval::since(
            Time::at(10),
            Duration::of(minutes: 30)
        );

        self::assertEquals('[10:00:00,10:30:00)', $range->format(IntervalFormat::Iso80000));
    }

    public function test_before_creates_expected_range(): void
    {
        $range = Interval::until(
            Time::at(10),
            Duration::of(minutes: 30)
        );

        self::assertEquals('[09:30:00,10:00:00)', $range->format(IntervalFormat::Iso80000));
    }

    public function test_around_creates_symmetric_range(): void
    {
        $range = Interval::around(
            Time::at(10),
            Duration::of(minutes: 20)
        );

        self::assertEquals('[09:50:00,10:10:00)', $range->format(IntervalFormat::Iso80000));
    }

    /* -------------------------------------------------
     * Duration & comparisons
     * ------------------------------------------------- */

    public function test_duration(): void
    {
        $range = Interval::between(Time::at(10), Time::at(11));

        self::assertEquals(
            Duration::of(minutes: 60),
            $range->duration
        );
    }

    public function test_same_duration(): void
    {
        $a = Interval::between(Time::at(10), Time::at(11));
        $b = Interval::between(Time::at(20), Time::at(21));

        self::assertTrue($a->sameDurationAs($b));
    }

    public function test_longer_and_shorter(): void
    {
        $a = Interval::between(Time::at(10), Time::at(12));
        $b = Interval::between(Time::at(10), Time::at(11));

        self::assertTrue($a->longerThan($b));
        self::assertTrue($a->longerThanOrEqual($b));
        self::assertTrue($b->shorterThan($a));
        self::assertTrue($b->shorterThanOrEqual($a));
    }

    /* -------------------------------------------------
     * contains (Time)
     * ------------------------------------------------- */

    public function test_contains_time_inside(): void
    {
        $range = Interval::between(Time::at(10), Time::at(12));

        self::assertTrue($range->includes(Time::at(11)));
        self::assertFalse($range->includes(Time::at(12))); // end excluded
    }

    /* -------------------------------------------------
     * overlaps
     * ------------------------------------------------- */

    public function test_overlaps_true(): void
    {
        $a = Interval::between(Time::at(10), Time::at(12));
        $b = Interval::between(Time::at(11), Time::at(13));

        self::assertTrue($a->overlaps($b));
    }

    public function test_overlaps_false(): void
    {
        $a = Interval::between(Time::at(10), Time::at(11));
        $b = Interval::between(Time::at(12), Time::at(13));

        self::assertFalse($a->overlaps($b));
    }

    /* -------------------------------------------------
     * abuts
     * ------------------------------------------------- */

    public function test_abuts_true(): void
    {
        $a = Interval::between(Time::at(10), Time::at(11));
        $b = Interval::between(Time::at(11), Time::at(12));

        self::assertTrue($a->abuts($b));
    }

    public function test_abuts_false(): void
    {
        $a = Interval::between(Time::at(10), Time::at(11));
        $b = Interval::between(Time::at(11, 1), Time::at(12));

        self::assertFalse($a->abuts($b));
    }

    /* -------------------------------------------------
     * intersect
     * ------------------------------------------------- */

    public function test_intersect(): void
    {
        $a = Interval::between(Time::at(10), Time::at(12));
        $b = Interval::between(Time::at(11), Time::at(13));

        $i = $a->intersect($b);

        self::assertNotNull($i);
        self::assertEquals('[11:00:00,12:00:00)', $i->format(IntervalFormat::Iso80000));
    }

    public function test_intersect_null_when_disjoint(): void
    {
        $a = Interval::between(Time::at(10), Time::at(11));
        $b = Interval::between(Time::at(12), Time::at(13));

        self::assertNull($a->intersect($b));
    }

    /* -------------------------------------------------
     * gap
     * ------------------------------------------------- */

    public function test_gap(): void
    {
        $a = Interval::between(Time::at(10), Time::at(11));
        $b = Interval::between(Time::at(12), Time::at(13));

        $gap = $a->gap($b);

        self::assertNotNull($gap);
        self::assertEquals('[11:00:00,12:00:00)', $gap->format(IntervalFormat::Iso80000));
    }

    public function test_gap_null_when_overlapping(): void
    {
        $a = Interval::between(Time::at(10), Time::at(12));
        $b = Interval::between(Time::at(11), Time::at(13));

        self::assertNull($a->gap($b));
    }

    /* -------------------------------------------------
     * splitForward
     * ------------------------------------------------- */

    public function test_split_forward_basic(): void
    {
        $range = Interval::between(Time::at(10), Time::at(12));
        $parts = $range->splitBy(Duration::of(minutes: 30));

        self::assertCount(4, $parts);
        self::assertEquals('[10:00:00,10:30:00)', $parts->get(0)->format(IntervalFormat::Iso80000));
        self::assertEquals('[10:30:00,11:00:00)', $parts->get(1)->format(IntervalFormat::Iso80000));
        self::assertEquals('[11:00:00,11:30:00)', $parts->get(2)->format(IntervalFormat::Iso80000));
        self::assertEquals('[11:30:00,12:00:00)', $parts->get(3)->format(IntervalFormat::Iso80000));
    }

    /* -------------------------------------------------
     * splitBackward
     * ------------------------------------------------- */

    public function test_split_backward_40_minute_duration(): void
    {
        $range = Interval::between(Time::at(9), Time::at(10));

        $splits = $range->splitBy(Duration::of(minutes: 40), Bound::End);

        self::assertCount(2, $splits);

        self::assertEquals('[09:20:00,10:00:00)', $splits->get(0)->format(IntervalFormat::Iso80000));
        self::assertEquals('[09:00:00,09:20:00)', $splits->get(1)->format(IntervalFormat::Iso80000));
    }

    public function test_split_with_collapsed(): void
    {
        $range = Interval::collapsed(Time::at(10));

        self::assertCount(0, $range->splitBy(Duration::of(minutes: 30)));
    }

    public function test_equals(): void
    {
        $range = Interval::between(Time::at(10), Time::at(12));
        $rangebis = Interval::since(Time::at(10), Duration::of(hours: 2));

        self::assertTrue($range->equals($rangebis));
        self::assertTrue($range->shorterThanOrEqual($rangebis));
        self::assertTrue($range->longerThanOrEqual($rangebis));
    }

    public function test_contains_time_range_fully_inside(): void
    {
        $a = Interval::between(Time::at(10), Time::at(14));
        $b = Interval::between(Time::at(11), Time::at(13));

        self::assertTrue($a->contains($b));
        self::assertFalse($b->contains($a));
    }

    public function test_contains_time_range_boundary_excluded(): void
    {
        $a = Interval::between(Time::at(10), Time::at(12));
        $b = Interval::between(Time::at(11), Time::at(12));

        self::assertTrue($a->contains($b));
    }

    public function test_contains_time_range_partial_overlap_false(): void
    {
        $a = Interval::between(Time::at(10), Time::at(12));
        $b = Interval::between(Time::at(11), Time::at(13));

        self::assertFalse($a->contains($b));
    }

    public function test_contains_time_range_disjoint(): void
    {
        $a = Interval::between(Time::at(10), Time::at(11));
        $b = Interval::between(Time::at(12), Time::at(13));

        self::assertFalse($a->contains($b));
    }

    public function test_contains_time_range_identical(): void
    {
        $a = Interval::between(Time::at(10), Time::at(12));
        $b = Interval::between(Time::at(10), Time::at(12));

        self::assertTrue($a->contains($b));
    }

    public function test_contains_time_range_wraparound(): void
    {
        $a = Interval::between(Time::at(22), Time::at(02));
        $b = Interval::between(Time::at(23), Time::at(01));

        self::assertTrue($a->contains($b));
    }

    public function test_contains_time_range_reverse(): void
    {
        $a = Interval::between(Time::at(23), Time::at(03));
        $b = Interval::between(Time::at(10), Time::at(11));

        self::assertFalse($a->contains($b));
    }

    public function test_range_forward(): void
    {
        $range = Interval::between(Time::at(hour: 9), Time::at(hour: 10));
        $times = iterator_to_array($range->steps(Duration::of(minutes: 15)));

        self::assertCount(4, $times);

        self::assertSame('09:00:00', $times[0]->toString());
        self::assertSame('09:15:00', $times[1]->toString());
        self::assertSame('09:30:00', $times[2]->toString());
        self::assertSame('09:45:00', $times[3]->toString());
    }

    public function test_range_backward(): void
    {
        $range = Interval::between(Time::at(hour: 9), Time::at(hour: 10));
        $times = iterator_to_array($range->steps(Duration::of(minutes: 15), Bound::End));

        self::assertCount(4, $times);

        self::assertSame('09:45:00', $times[0]->toString());
        self::assertSame('09:30:00', $times[1]->toString());
        self::assertSame('09:15:00', $times[2]->toString());
        self::assertSame('09:00:00', $times[3]->toString());
    }

    public function test_range_with_zero_duration(): void
    {
        $range = Interval::between(Time::at(hour: 9), Time::at(hour: 10));
        $this->expectException(InvalidDuration::class);

        iterator_to_array($range->steps(Duration::zero(), Bound::End));
    }

    public function test_range_with_collapsed_interval(): void
    {
        $range = Interval::collapsed(Time::at(hour: 9));
        self::assertCount(0, iterator_to_array($range->steps(Duration::of(hours: 3), Bound::End)));
    }

    public function test_expand(): void
    {
        $range = Interval::between(Time::at(hour: 10), Time::at(hour: 12));
        $expanded = $range->expand(Duration::of(hours: 1));

        self::assertSame('[09:00:00,13:00:00)', $expanded->format(IntervalFormat::Iso80000));
    }

    public function test_expand_wraps_around_midnight(): void
    {
        $range = Interval::between(Time::at(hour: 0, minute: 2), Time::at(hour: 23, minute: 58));
        $expanded = $range->expand(Duration::of(minutes: 5));

        self::assertSame('[23:57:00,00:03:00)', $expanded->format(IntervalFormat::Iso80000));
    }

    public function test_expand_can_shrink_range(): void
    {
        $range = Interval::between(Time::at(hour: 10), Time::at(hour: 14));
        $shrunk = $range->expand(Duration::of(hours: 1)->negated());

        self::assertSame('[11:00:00,13:00:00)', $shrunk->format(IntervalFormat::Iso80000));
    }

    public function test_expand_by_24_hours_returns_same_range(): void
    {
        $range = Interval::between(
            Time::at(hour: 10),
            Time::at(hour: 12),
        );

        $expanded = $range->expand(Duration::of(hours: 24));

        self::assertTrue($range->equals($expanded));
    }

    public function test_expand_by_multiple_of_24_hours_returns_same_range(): void
    {
        $range = Interval::between(
            Time::at(hour: 22),
            Time::at(hour: 3),
        );

        $expanded = $range->expand(Duration::of(hours: 48));

        self::assertTrue($range->equals($expanded));
    }

    public function test_expand_can_collapse_range_to_empty(): void
    {
        $range = Interval::between(Time::at(hour: 10), Time::at(hour: 12));
        $collapsed = $range->expand(Duration::of(hours: 1)->negated());

        self::assertSame('[11:00:00,11:00:00)', $collapsed->format(IntervalFormat::Iso80000));
    }

    public function test_collapsed_creates_zero_duration_range(): void
    {
        $time = Time::at(hour: 10);

        $range = Interval::collapsed($time);

        self::assertEquals($time, $range->start);
        self::assertEquals($time, $range->end);
        self::assertTrue($range->duration->isEmpty());
        self::assertSame(IntervalType::Collapsed, $range->type);
    }

    public function test_circular_creates_full_day_range(): void
    {
        $time = Time::at(hour: 10);

        $interval = Interval::circular($time);

        self::assertEquals($time, $interval->start);
        self::assertEquals($time, $interval->end);
        self::assertTrue($interval->duration->equals(Duration::of(hours: 24)));
        self::assertSame(IntervalType::Circular, $interval->type);
    }

    public function testStartingOnReturnsSameInstanceWhenUnchanged(): void
    {
        $range = Interval::between(Time::at(10), Time::at(12));

        self::assertSame($range, $range->startingOn(Time::at(10)));
    }

    public function testStartingOnChangesStart(): void
    {
        $range = Interval::between(Time::at(10), Time::at(12));

        $updated = $range->startingOn(Time::at(9));

        self::assertSame('[09:00:00,12:00:00)', $updated->format(IntervalFormat::Iso80000));
    }

    public function testEndingOnReturnsSameInstanceWhenUnchanged(): void
    {
        $range = Interval::between(Time::at(10), Time::at(12));

        self::assertSame(
            $range,
            $range->endingOn(Time::at(12))
        );
    }

    public function testEndingOnChangesEnd(): void
    {
        $range = Interval::between(Time::at(10), Time::at(12));

        $updated = $range->endingOn(Time::at(14));

        self::assertSame('[10:00:00,14:00:00)', $updated->format(IntervalFormat::Iso80000));
    }

    public function testShiftReturnsSameInstanceForZeroDuration(): void
    {
        $range = Interval::between(Time::at(10), Time::at(12));

        self::assertSame(
            $range,
            $range->shift(Duration::zero())
        );
    }

    public function testShiftMovesEntireRangeForward(): void
    {
        $range = Interval::between(Time::at(10), Time::at(12));

        $shifted = $range->shift(Duration::of(hours: 2));

        self::assertSame('[12:00:00,14:00:00)', $shifted->format(IntervalFormat::Iso80000));
    }

    public function testShiftSupportsCircularWrapping(): void
    {
        $range = Interval::between(Time::at(22), Time::at(2));

        $shifted = $range->shift(Duration::of(hours: 3));

        self::assertSame('[01:00:00,05:00:00)', $shifted->format(IntervalFormat::Iso80000));
    }

    public function testShiftStartMovesOnlyStart(): void
    {
        $range = Interval::between(Time::at(10), Time::at(12));

        $updated = $range->shiftBound(bound: Bound::Start, duration: Duration::of(hours: 1));

        self::assertSame('[11:00:00,12:00:00)', $updated->format(IntervalFormat::Iso80000));
    }

    public function testShiftEndMovesOnlyEnd(): void
    {
        $range = Interval::between(Time::at(10), Time::at(12));

        $updated = $range->shiftBound(bound: Bound::End, duration: Duration::of(hours: 2));

        self::assertSame('[10:00:00,14:00:00)', $updated->format(IntervalFormat::Iso80000));
    }

    public function testLastingFromStartChangesDuration(): void
    {
        $range = Interval::between(Time::at(10), Time::at(12));

        $updated = $range->lasting(from: Bound::Start, duration: Duration::of(hours: 5));

        self::assertSame('[10:00:00,15:00:00)', $updated->format(IntervalFormat::Iso80000));
    }

    public function testLastingFromStartSupportsCircularWrapping(): void
    {
        $range = Interval::between(Time::at(22), Time::at(23));

        $updated = $range->lasting(from: Bound::Start, duration: Duration::of(hours: 4));

        self::assertSame('[22:00:00,02:00:00)', $updated->format(IntervalFormat::Iso80000));
    }

    public function testLastingFromEndChangesDuration(): void
    {
        $range = Interval::between(Time::at(10), Time::at(12));

        $updated = $range->lasting(from: Bound::End, duration: Duration::of(hours: 5));

        self::assertSame('[07:00:00,12:00:00)', $updated->format(IntervalFormat::Iso80000));
    }

    public function testLastingFromEndSupportsCircularWrapping(): void
    {
        $range = Interval::between(Time::at(1), Time::at(3));

        $updated = $range->lasting(from: Bound::End, duration: Duration::of(hours: 6));

        self::assertSame('[21:00:00,03:00:00)', $updated->format(IntervalFormat::Iso80000));
    }

    public function testInvertSwapsStartAndEnd(): void
    {
        $range = Interval::between(
            Time::at(10),
            Time::at(14),
        );

        self::assertSame(
            '[14:00:00,10:00:00)',
            $range->complement()->format(IntervalFormat::Iso80000)
        );
    }

    public function testInvertOfCollapsedRangeReturnsCircularRange(): void
    {
        $range = Interval::collapsed(Time::at(10));

        $inverted = $range->complement();

        self::assertSame(IntervalType::Circular, $inverted->type);
    }

    public function testInvertOfCircularRangeReturnsCollapsedRange(): void
    {
        $range = Interval::circular(Time::at(10));

        $inverted = $range->complement();

        self::assertSame(IntervalType::Collapsed, $inverted->type);
    }

    public function test_full_day(): void
    {
        $range = Interval::fullDay();
        $inverted = $range->complement();

        self::assertSame(IntervalType::Collapsed, $inverted->type);
        self::assertTrue(Time::midnight()->equals($inverted->start));
        self::assertFalse($inverted->includes($inverted->start));
        self::assertTrue($range->includes($range->end));
    }

    public function testInvertIsAnInvolution(): void
    {
        $range = Interval::between(
            Time::at(22),
            Time::at(2),
        );

        self::assertTrue(
            $range
                ->complement()
                ->complement()
                ->equals($range)
        );
    }

    public function testInvertProducesComplementaryDuration(): void
    {
        $range = Interval::between(
            Time::at(22),
            Time::at(2),
        );

        $total = $range
            ->duration
            ->sum($range->complement()->duration);

        self::assertTrue(
            $total->equals(Duration::of(hours: 24))
        );
    }

    /**
     * @param non-empty-string $input
     * @param non-empty-string $expected
     *
     * @throws InvalidDuration
     * @throws InvalidTime
     * @throws TimeException
     */
    #[DataProvider('iso8601ValidProvider')]
    public function test_from_iso8601(string $input, string $expected): void
    {
        self::assertSame($expected, Interval::fromIso8601($input)->format(IntervalFormat::Iso80000));
    }

    /**
     * @return array<non-empty-string, array{0: non-empty-string, 1: non-empty-string}>
     */
    public static function iso8601ValidProvider(): array
    {
        return [
            'valid simple with start date' => ['10:00:00/PT1H', '[10:00:00,11:00:00)'],
            'valid with spaces' => [' 10:00:00/PT1H ', '[10:00:00,11:00:00)'],
            'valid simple with end date' => ['PT1H/11:00:00', '[10:00:00,11:00:00)'],
            'valid with end date and space' => [' PT1H/11:00:00 ', '[10:00:00,11:00:00)'],
            'valid with start and end time' => ['10:00:00/11:00:00', '[10:00:00,11:00:00)'],
            'valid with start and end time with space' => [' 10:00:00 / 11:00:00 ', '[10:00:00,11:00:00)'],
        ];
    }

    /**
     * @param non-empty-string $input
     *
     * @throws InvalidInterval
     */
    #[DataProvider('iso8601InvalidProvider')]
    public function test_from_iso8601_invalid(string $input): void
    {
        $this->expectException(InvalidInterval::class);

        Interval::fromIso8601($input);
    }

    /**
     * @return array<non-empty-string, array{0: string}>
     */
    public static function iso8601InvalidProvider(): array
    {
        return [
            'missing slash' => ['10:00:00PT1H'],
            'empty string' => [''],
            'invalid start time' => ['invalid/PT1H'],
            'invalid end duration' => ['10:00:00/invalid'],
            'extra segments' => ['10:00:00/PT1H/PT2H'],
            'invalid end time' => ['PT2H/invalid'],
            'invalid start duration' => ['Pinvalid/09:00:00'],
        ];
    }

    /**
     * @param non-empty-string $input
     * @param non-empty-string $expectedIso8601
     *
     * @throws InvalidInterval
     */
    #[DataProvider('iso80000ValidProvider')]
    public function test_from_iso80000(string $input, string $expectedIso8601): void
    {
        self::assertSame($expectedIso8601, Interval::fromIso80000($input)->format());
    }

    /**
     * @return array<non-empty-string, array{0: non-empty-string, 1: non-empty-string}>
     */
    public static function iso80000ValidProvider(): array
    {
        return [
            'full range' => ['[10:00:00,12:00:00)', '10:00:00/PT2H'],
            'open start' => ['[,12:00:00)', '00:00:00/PT12H'],
            'open end' => ['[10:00:00,)', '10:00:00/PT14H'],
            'with spaces' => ['[ 10:00:00 , 12:00:00 )', '10:00:00/PT2H'],
        ];
    }

    /**
     * @param non-empty-string $input
     *
     * @throws InvalidInterval
     */
    #[DataProvider('iso80000InvalidProvider')]
    public function test_from_iso80000_invalid(string $input): void
    {
        $this->expectException(InvalidInterval::class);

        Interval::fromIso80000($input);
    }

    /**
     * @return array<non-empty-string, array{0: string}>
     */
    public static function iso80000InvalidProvider(): array
    {
        return [
            'unsupported boudaries' => ['[10:00:00,12:00:00]'],
            'missing at least one boundary value' => ['[,)'],
            'missing brackets' => ['10:00:00,12:00:00'],
            'invalid format' => ['[invalid]'],
            'invalid start' => ['[invalid,12:00:00)'],
        ];
    }

    /**
     * @param non-empty-string $input
     * @param non-empty-string $expectedIso8601
     *
     * @throws InvalidInterval
     */
    #[DataProvider('bourbakiValidProvider')]
    public function test_from_bouraki(string $input, string $expectedIso8601): void
    {
        self::assertSame($expectedIso8601, Interval::fromBourbaki($input)->format());
    }

    /**
     * @return array<non-empty-string, array{0: non-empty-string, 1: non-empty-string}>
     */
    public static function bourbakiValidProvider(): array
    {
        return [
            'full range' => ['[10:00:00,12:00:00[', '10:00:00/PT2H'],
            'open start' => ['[,12:00:00[', '00:00:00/PT12H'],
            'open end' => ['[10:00:00,[', '10:00:00/PT14H'],
            'with spaces' => ['[ 10:00:00 , 12:00:00 [', '10:00:00/PT2H'],
        ];
    }

    /**
     * @param non-empty-string $input
     * @param class-string<Throwable> $expectedException
     *
     * @throws InvalidTime
     * @throws TimeException
     */
    #[DataProvider('bourbakiInvalidProvider')]
    public function test_from_bourbaki_invalid(string $input, string $expectedException): void
    {
        $this->expectException($expectedException);

        Interval::fromBourbaki($input);
    }

    /**
     * @return array<non-empty-string, array{0: string, 1: ?class-string}>
     */
    public static function bourbakiInvalidProvider(): array
    {
        return [
            'unsupported boudaries' => ['[10:00:00,12:00:00', TimeException::class],
            'missing at least one boundary value' => ['[,[', TimeException::class],
            'missing brackets' => ['10:00:00,12:00:00', TimeException::class],
            'invalid format' => ['[invalid', TimeException::class],
            'invalid start' => ['[invalid,12:00:00[', TimeException::class],
        ];
    }

    public function test_interval_can_be_serialized_and_unserialized(): void
    {
        $interval = Interval::fromIso8601('12:34:56/-PT23H30S');
        $restored = unserialize(serialize($interval));

        self::assertInstanceOf(Interval::class, $restored);
        self::assertEquals($interval, $restored);
    }

    public function test_duration_can_be_json_serialized(): void
    {
        $interval = Interval::between(Time::at(22), Time::at(2));

        self::assertSame('"22:00:00.000000/PT4H"', json_encode($interval, JSON_UNESCAPED_SLASHES));
    }

    /**
     * @param list<non-empty-string> $expected
     *
     * @throws InvalidTime
     */
    #[DataProvider('splitAtProvider')]
    public function test_split_at(Interval $interval, Time $split, array $expected): void
    {
        self::assertSame($expected, $interval->splitAt($split)->allFormatted());
    }

    /**
     * @throws InvalidTime
     *
     * @return array<non-empty-string, array{0: Interval, 1: Time, 2: list<non-empty-string>}>
     */
    public static function splitAtProvider(): array
    {
        return [
            'collapsed interval returns empty set' => [
                Interval::between(Time::at(hour: 10), Time::at(hour: 10)),
                Time::at(hour: 10),
                [],
            ],
            'split inside interval' => [
                Interval::between(Time::at(hour: 10), Time::noon()),
                Time::at(hour: 11),
                ['10:00:00/PT1H', '11:00:00/PT1H'],
            ],

            'split at start returns original interval' => [
                Interval::between(Time::at(hour: 10), Time::noon()),
                Time::at(hour: 10),
                ['10:00:00/PT2H'],
            ],

            'split at end returns original interval' => [
                Interval::between(Time::at(hour: 10), Time::noon()),
                Time::noon(),
                ['10:00:00/PT2H'],
            ],
            'split outside interval returns empty set' => [
                Interval::between(Time::at(hour: 10), Time::noon()),
                Time::at(hour: 13),
                ['10:00:00/PT2H'],
            ],
        ];
    }

    #[DataProvider('intervalStateProvider')]
    public function test_interval_state(Interval $interval, IntervalType $type): void
    {
        self::assertSame($type, $interval->type);
    }

    /**
     * @throws InvalidDuration
     * @throws InvalidTime
     *
     * @return iterable<non-empty-string, array{interval: Interval, type:IntervalType}>
     */
    public static function intervalStateProvider(): iterable
    {
        yield 'interval is circular' => [
            'interval' => Interval::circular(Time::at(hour: 10)),
            'type' => IntervalType::Circular,
        ];

        yield 'interval is linear' => [
            'interval' => Interval::between(Time::noon(), Time::at(18)),
            'type' => IntervalType::Linear,
        ];

        yield 'interval overflows' => [
            'interval' => Interval::between(Time::at(22), Time::at(6)),
            'type' => IntervalType::Overflow,
        ];

        yield 'interval collapsed' => [
            'interval' => Interval::collapsed(Time::at(hour: 10)),
            'type' => IntervalType::Collapsed,
        ];
    }

    public function test_until_with_negative_duration(): void
    {
        $interval = Interval::until(Time::at(hour: 10), Duration::fromIso8601('-PT3H'));
        self::assertSame(1, $interval->duration->sign);
        self::assertEquals($interval->duration, Duration::of(hours: 21));
    }

    public function test_around_with_negative_duration(): void
    {
        $interval = Interval::around(Time::at(hour: 10, minute: 30), Duration::fromIso8601('-PT1H'));
        self::assertSame(1, $interval->duration->sign);
        self::assertEquals($interval->duration, Duration::of(hours: 23));
        self::assertEquals($interval->start, Time::at(hour: 11));
        self::assertEquals($interval->end, Time::at(hour: 10, minute: 00));
    }

    public function test_it_can_be_converted_to_using_php_native_objects(): void
    {
        $class = new class () extends DateTimeImmutable {};
        $timeZoneName = 'Africa/Brazzaville';

        $interval = Interval::between(Time::noon(), Time::at(18))->toNative(new $class('2025-03-02 23:12:59', new DateTimeZone($timeZoneName)));
        self::assertInstanceOf($class::class, $interval['startDate']);
        self::assertSame($interval['startDate']->getTimezone()->getName(), $timeZoneName);
        self::assertSame('2025-03-02 12:00:00', $interval['startDate']->format('Y-m-d H:i:s'));
        self::assertEquals(new DateInterval('PT6H'), $interval['interval']);
    }

    public function test_splitting_is_coherent(): void
    {
        $interval = Interval::between(Time::at(hour: 9), Time::at(hour: 10));
        $duration = Duration::of(minutes: 10);
        $steps = $interval->steps($duration, Bound::End);

        self::assertEquals($interval->splitAt(...$steps)->sorted(), $interval->splitBy($duration, Bound::End)->sorted());
    }
}
