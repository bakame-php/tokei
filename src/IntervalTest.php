<?php

declare(strict_types=1);

namespace Bakame\Tokei;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Interval::class)]
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

        self::assertEquals('[10:00:00,10:30:00)', $range->format());
    }

    public function test_before_creates_expected_range(): void
    {
        $range = Interval::until(
            Time::at(10),
            Duration::of(minutes: 30)
        );

        self::assertEquals('[09:30:00,10:00:00)', $range->format());
    }

    public function test_around_creates_symmetric_range(): void
    {
        $range = Interval::around(
            Time::at(10),
            Duration::of(minutes: 20)
        );

        self::assertEquals('[09:50:00,10:10:00)', $range->format());
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
        self::assertEquals('[11:00:00,12:00:00)', $i->format());
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
        self::assertEquals('[11:00:00,12:00:00)', $gap->format());
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

        $parts = iterator_to_array(
            $range->splitForward(Duration::of(minutes: 30))
        );

        self::assertCount(4, $parts);

        self::assertEquals('[10:00:00,10:30:00)', $parts[0]->format());
        self::assertEquals('[10:30:00,11:00:00)', $parts[1]->format());
        self::assertEquals('[11:00:00,11:30:00)', $parts[2]->format());
        self::assertEquals('[11:30:00,12:00:00)', $parts[3]->format());
    }

    /* -------------------------------------------------
     * splitBackward
     * ------------------------------------------------- */

    public function test_split_backward_40_minute_duration(): void
    {
        $range = Interval::between(Time::at(9), Time::at(10));

        $splits = iterator_to_array(
            $range->splitBackward(Duration::of(minutes: 40))
        );

        self::assertCount(2, $splits);

        self::assertEquals('[09:20:00,10:00:00)', $splits[0]->format());
        self::assertEquals('[09:00:00,09:20:00)', $splits[1]->format());
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
        $times = iterator_to_array(
            $range->rangeForward(Duration::of(minutes: 15))
        );

        self::assertCount(5, $times);

        self::assertSame('09:00:00', $times[0]->format());
        self::assertSame('09:15:00', $times[1]->format());
        self::assertSame('09:30:00', $times[2]->format());
        self::assertSame('09:45:00', $times[3]->format());
        self::assertSame('10:00:00', $times[4]->format());
    }

    public function test_range_backward(): void
    {
        $range = Interval::between(Time::at(hour: 9), Time::at(hour: 10));
        $times = iterator_to_array(
            $range->rangeBackward(Duration::of(minutes: 15))
        );

        self::assertCount(5, $times);

        self::assertSame('10:00:00', $times[0]->format());
        self::assertSame('09:45:00', $times[1]->format());
        self::assertSame('09:30:00', $times[2]->format());
        self::assertSame('09:15:00', $times[3]->format());
        self::assertSame('09:00:00', $times[4]->format());
    }

    public function test_expand(): void
    {
        $range = Interval::between(
            Time::at(hour: 10),
            Time::at(hour: 12),
        );

        $expanded = $range->expand(Duration::of(hours: 1));

        self::assertSame(
            '[09:00:00,13:00:00)',
            $expanded->format(),
        );
    }

    public function test_expand_wraps_around_midnight(): void
    {
        $range = Interval::between(
            Time::at(hour: 0, minute: 2),
            Time::at(hour: 23, minute: 58),
        );

        $expanded = $range->expand(Duration::of(minutes: 5));

        self::assertSame(
            '[23:57:00,00:03:00)',
            $expanded->format(),
        );
    }

    public function test_expand_can_shrink_range(): void
    {
        $range = Interval::between(
            Time::at(hour: 10),
            Time::at(hour: 14),
        );

        $shrunk = $range->expand(
            Duration::of(hours: 1)->negate()
        );

        self::assertSame(
            '[11:00:00,13:00:00)',
            $shrunk->format(),
        );
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
        $collapsed = $range->expand(Duration::of(hours: 1)->negate());

        self::assertSame('[11:00:00,11:00:00)', $collapsed->format());
    }

    public function test_collapsed_creates_zero_duration_range(): void
    {
        $time = Time::at(hour: 10);

        $range = Interval::collapsed($time);

        self::assertSame($time, $range->start);
        self::assertSame($time, $range->end);
        self::assertTrue($range->duration->isEmpty());
        self::assertTrue($range->isCollapsed());
        self::assertFalse($range->isCircular());
    }

    public function test_circular_creates_full_day_range(): void
    {
        $time = Time::at(hour: 10);

        $range = Interval::circular($time);

        self::assertSame($time, $range->start);
        self::assertSame($time, $range->end);
        self::assertTrue($range->duration->equals(Duration::of(hours: 24)));
        self::assertFalse($range->isCollapsed());
        self::assertTrue($range->isCircular());
    }

    public function testStartingOnReturnsSameInstanceWhenUnchanged(): void
    {
        $range = Interval::between(Time::at(10), Time::at(12));

        self::assertSame(
            $range,
            $range->startingOn(Time::at(10))
        );
    }

    public function testStartingOnChangesStart(): void
    {
        $range = Interval::between(Time::at(10), Time::at(12));

        $updated = $range->startingOn(Time::at(9));

        self::assertSame('[09:00:00,12:00:00)', $updated->format());
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

        self::assertSame('[10:00:00,14:00:00)', $updated->format());
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

        self::assertSame('[12:00:00,14:00:00)', $shifted->format());
    }

    public function testShiftSupportsCircularWrapping(): void
    {
        $range = Interval::between(Time::at(22), Time::at(2));

        $shifted = $range->shift(Duration::of(hours: 3));

        self::assertSame('[01:00:00,05:00:00)', $shifted->format());
    }

    public function testShiftStartMovesOnlyStart(): void
    {
        $range = Interval::between(Time::at(10), Time::at(12));

        $updated = $range->shiftStart(Duration::of(hours: 1));

        self::assertSame('[11:00:00,12:00:00)', $updated->format());
    }

    public function testShiftEndMovesOnlyEnd(): void
    {
        $range = Interval::between(Time::at(10), Time::at(12));

        $updated = $range->shiftEnd(Duration::of(hours: 2));

        self::assertSame('[10:00:00,14:00:00)', $updated->format());
    }

    public function testLastingFromStartChangesDuration(): void
    {
        $range = Interval::between(Time::at(10), Time::at(12));

        $updated = $range->lastingFromStart(Duration::of(hours: 5));

        self::assertSame('[10:00:00,15:00:00)', $updated->format());
    }

    public function testLastingFromStartSupportsCircularWrapping(): void
    {
        $range = Interval::between(Time::at(22), Time::at(23));

        $updated = $range->lastingFromStart(Duration::of(hours: 4));

        self::assertSame('[22:00:00,02:00:00)', $updated->format());
    }

    public function testLastingFromEndChangesDuration(): void
    {
        $range = Interval::between(Time::at(10), Time::at(12));

        $updated = $range->lastingFromEnd(Duration::of(hours: 5));

        self::assertSame('[07:00:00,12:00:00)', $updated->format());
    }

    public function testLastingFromEndSupportsCircularWrapping(): void
    {
        $range = Interval::between(Time::at(1), Time::at(3));

        $updated = $range->lastingFromEnd(Duration::of(hours: 6));

        self::assertSame('[21:00:00,03:00:00)', $updated->format());
    }

    public function testLastingFromStartRejectsNegativeDuration(): void
    {
        $range = Interval::between(Time::at(10), Time::at(12));

        $this->expectException(InvalidDuration::class);

        $range->lastingFromStart(
            Duration::of(hours: -1)
        );
    }

    public function testLastingFromEndRejectsNegativeDuration(): void
    {
        $range = Interval::between(Time::at(10), Time::at(12));

        $this->expectException(InvalidDuration::class);

        $range->lastingFromEnd(
            Duration::of(hours: -1)
        );
    }

    public function testInvertSwapsStartAndEnd(): void
    {
        $range = Interval::between(
            Time::at(10),
            Time::at(14),
        );

        self::assertSame(
            '[14:00:00,10:00:00)',
            $range->complement()->format()
        );
    }

    public function testInvertOfCollapsedRangeReturnsCircularRange(): void
    {
        $range = Interval::collapsed(Time::at(10));

        $inverted = $range->complement();

        self::assertTrue($inverted->isCircular());
    }

    public function testInvertOfCircularRangeReturnsCollapsedRange(): void
    {
        $range = Interval::circular(Time::at(10));

        $inverted = $range->complement();

        self::assertTrue($inverted->isCollapsed());
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
}
