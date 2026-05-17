<?php

declare(strict_types=1);

namespace Bakame\Tokei;

use PHPUnit\Framework\TestCase;

final class IntervalSetTest extends TestCase
{
    public function test_it_can_be_empty(): void
    {
        $set = new IntervalSet();

        self::assertTrue($set->isEmpty());
        self::assertCount(0, $set);
        self::assertNull($set->first());
        self::assertNull($set->last());
    }

    public function test_it_preserves_order(): void
    {
        $a = Interval::between(Time::at(10), Time::at(11));
        $b = Interval::between(Time::at(12), Time::at(13));

        $set = new IntervalSet($a, $b);

        self::assertSame($a, $set->first());
        self::assertSame($b, $set->last());
    }

    public function test_get_supports_negative_offsets(): void
    {
        $a = Interval::between(Time::at(10), Time::at(11));
        $b = Interval::between(Time::at(12), Time::at(13));

        $set = new IntervalSet($a, $b);

        self::assertSame($b, $set->get(-1));
        self::assertSame($a, $set->get(-2));
        self::assertNull($set->get(-3));
    }

    public function test_push_returns_same_instance_when_empty(): void
    {
        $set = new IntervalSet();

        self::assertSame($set, $set->push());
    }

    public function test_push_appends_intervals(): void
    {
        $a = Interval::between(Time::at(10), Time::at(11));
        $b = Interval::between(Time::at(12), Time::at(13));

        $set = (new IntervalSet($a))->push($b);

        self::assertCount(2, $set);
        self::assertSame($a, $set->first());
        self::assertSame($b, $set->last());
    }

    public function test_normalize_sorts_intervals(): void
    {
        $a = Interval::between(Time::at(12), Time::at(14));
        $b = Interval::between(Time::at(10), Time::at(11));

        $normalized = (new IntervalSet($a, $b))->union();

        self::assertTrue($normalized->first()?->equals($b));
        self::assertTrue($normalized->last()?->equals($a));
    }

    public function test_normalize_merges_overlapping_intervals(): void
    {
        $a = Interval::between(Time::at(10), Time::at(12));
        $b = Interval::between(Time::at(11), Time::at(14));

        $normalized = (new IntervalSet($a, $b))->union();

        self::assertCount(1, $normalized);

        $expected = Interval::between(Time::at(10), Time::at(14));
        $first = $normalized->first();
        self::assertInstanceOf(Interval::class, $first);
        self::assertTrue($expected->equals($first));
    }

    public function test_normalize_merges_abutting_intervals(): void
    {
        $a = Interval::between(Time::at(10), Time::at(12));
        $b = Interval::between(Time::at(12), Time::at(14));

        $normalized = (new IntervalSet($a, $b))->union();

        self::assertCount(1, $normalized);

        $expected = Interval::between(Time::at(10), Time::at(14));

        $first = $normalized->first();
        self::assertInstanceOf(Interval::class, $first);
        self::assertTrue($expected->equals($first));
    }

    public function test_normalize_keeps_disjoint_intervals(): void
    {
        $a = Interval::between(Time::at(10), Time::at(11));
        $b = Interval::between(Time::at(13), Time::at(14));

        $normalized = (new IntervalSet($a, $b))->union();

        self::assertCount(2, $normalized);
    }

    public function test_normalize_handles_circular_intervals(): void
    {
        $a = Interval::between(Time::at(22), Time::at(2));
        $b = Interval::between(Time::at(1), Time::at(3));

        $normalized = (new IntervalSet($a, $b))->union();

        self::assertCount(1, $normalized);

        $expected = Interval::between(Time::at(22), Time::at(3));

        $first = $normalized->first();
        self::assertInstanceOf(Interval::class, $first);
        self::assertTrue($expected->equals($first));
    }

    public function test_difference_of_disjoint_intervals_returns_original(): void
    {
        $a = Interval::between(Time::at(10), Time::at(20));
        $b = Interval::between(Time::at(10)->add(Duration::of(hours: 10)), Time::at(20)->add(Duration::of(hours: 10)));

        $result = (new IntervalSet($a))->difference(new IntervalSet($b));

        self::assertCount(1, $result);
        self::assertInstanceOf(Interval::class, $result->first());
        self::assertTrue($a->equals($result->first()));
    }

    public function test_difference_of_fully_contained_interval_splits_interval(): void
    {
        $a = Interval::between(Time::at(10), Time::at(20));
        $b = Interval::between(Time::at(13), Time::at(17));

        $result = (new IntervalSet($a))->difference(new IntervalSet($b));

        self::assertCount(2, $result);

        $first = $result->first();
        self::assertInstanceOf(Interval::class, $first);
        self::assertTrue(Interval::between(Time::at(10), Time::at(13))->equals($first));

        $second = $result->get(1);
        self::assertInstanceOf(Interval::class, $second);
        self::assertTrue(Interval::between(Time::at(17), Time::at(20))->equals($second));
    }

    public function test_difference_of_overlapping_left_side(): void
    {
        $a = Interval::between(Time::at(10), Time::at(20));
        $b = Interval::between(Time::at(5), Time::at(15));

        $result = (new IntervalSet($a))->difference(new IntervalSet($b));

        self::assertCount(1, $result);
        $first = $result->first();
        self::assertInstanceOf(Interval::class, $first);
        self::assertTrue(Interval::between(Time::at(15), Time::at(20))->equals($first));
    }

    public function test_difference_of_overlapping_right_side(): void
    {
        $a = Interval::between(Time::at(10), Time::at(20));
        $b = Interval::between(Time::at(15), Time::at(23)->add(Duration::of(hours: 2)));

        $result = (new IntervalSet($a))->difference(new IntervalSet($b));

        self::assertCount(1, $result);
        $first = $result->first();
        self::assertInstanceOf(Interval::class, $first);
        self::assertTrue(Interval::between(Time::at(10), Time::at(15))->equals($first));
    }

    public function test_difference_of_identical_intervals_returns_empty(): void
    {
        $a = Interval::between(Time::at(10), Time::at(20));

        $result = (new IntervalSet($a))->difference(new IntervalSet($a));

        self::assertTrue($result->isEmpty());
    }

    public function test_difference_of_covering_interval_returns_empty(): void
    {
        $a = Interval::between(Time::at(10), Time::at(20));
        $b = Interval::between(Time::at(5), Time::at(2)->add(Duration::of(hours: 1)));

        $result = (new IntervalSet($a))->difference(new IntervalSet($b));

        self::assertTrue($result->isEmpty());
    }

    public function test_normalize_is_idempotent(): void
    {
        $a = Interval::between(Time::at(10), Time::at(12));
        $b = Interval::between(Time::at(11), Time::at(14));

        $set = new IntervalSet($a, $b);

        $normalized = $set->union();

        self::assertEquals(
            $normalized,
            $normalized->union()
        );
    }

    public function test_difference_with_empty_set_returns_original(): void
    {
        $a = Interval::between(Time::at(10), Time::at(20));

        $result = (new IntervalSet($a))
            ->difference(new IntervalSet());

        self::assertCount(1, $result);
        $first = $result->first();
        self::assertInstanceOf(Interval::class, $first);
        self::assertTrue($a->equals($first));
    }

    public function test_difference_handles_circular_intervals(): void
    {
        $a = Interval::between(Time::at(22), Time::at(4));
        $b = Interval::between(Time::at(23), Time::at(1));

        $result = (new IntervalSet($a))
            ->difference(new IntervalSet($b));

        self::assertCount(2, $result);
        $first = $result->first();
        self::assertInstanceOf(Interval::class, $first);
        self::assertTrue(Interval::between(Time::at(22), Time::at(23))->equals($first));

        $second = $result->get(1);
        self::assertInstanceOf(Interval::class, $second);
        self::assertTrue(Interval::between(Time::at(1), Time::at(4))->equals($second));
    }

    private function i(int $start, int $end): Interval
    {
        return Interval::between(Time::at($start), Time::at($end));
    }

    public function test_map_transforms_intervals(): void
    {
        $set = new IntervalSet(
            $this->i(1, 2),
            $this->i(3, 4),
        );

        $result = $set->map(
            fn (Interval $i): string =>
                $i->start->hour.'-'.$i->end->hour
        );

        self::assertSame(
            [
                '1-2',
                '3-4',
            ],
            iterator_to_array($result)
        );
    }

    public function test_map_preserves_order(): void
    {
        $set = new IntervalSet(
            $this->i(10, 12),
            $this->i(1, 3),
            $this->i(5, 7),
        );

        $result = $set->map(fn (Interval $i) => $i);

        self::assertSame(
            array_map(
                fn (Interval $i) => $i->start->toMicroOfDay(),
                $set->all()
            ),
            array_map(
                fn (Interval $i) => $i->start->toMicroOfDay(),
                iterator_to_array($result)
            )
        );
    }

    public function test_filter_keeps_matching_intervals(): void
    {
        $set = new IntervalSet(
            $this->i(1, 2),
            $this->i(3, 4),
            $this->i(5, 6),
        );

        $filtered = $set->filter(
            fn (Interval $i): bool =>
                $i->start->toMicroOfDay() >= Time::at(3)->toMicroOfDay()
        );

        self::assertCount(2, $filtered);

        self::assertSame(
            [3, 5],
            array_map(
                fn (Interval $i) => $i->start->hour,
                $filtered->all()
            )
        );
    }

    public function test_filter_returns_same_instance_if_no_change(): void
    {
        $set = new IntervalSet(
            $this->i(1, 2),
            $this->i(3, 4),
        );

        $filtered = $set->filter(fn () => true);

        self::assertSame($set, $filtered);
    }

    public function test_filter_returns_empty_set_when_no_match(): void
    {
        $set = new IntervalSet(
            $this->i(1, 2),
            $this->i(3, 4),
        );

        $filtered = $set->filter(fn () => false);

        self::assertTrue($filtered->isEmpty());
    }

    public function test_reduce_accumulates_values(): void
    {
        $set = new IntervalSet(
            $this->i(1, 2),
            $this->i(3, 5),
        );

        $totalDuration = $set->reduce(
            fn (int $carry, Interval $i): int =>
                $carry + ($i->end->hour - $i->start->hour),
            0
        );

        self::assertSame(
            (2 - 1) + (5 - 3),
            $totalDuration
        );
    }

    public function test_reduce_without_initial_value(): void
    {
        $set = new IntervalSet(
            $this->i(10, 20),
            $this->i(21, 23),
        );

        $result = $set->reduce(
            fn (?Interval $carry, Interval $i): Interval =>
                $carry ?? $i,
            null
        );

        self::assertInstanceOf(Interval::class, $result);

        self::assertSame(
            $this->i(10, 20)->start->toMicroOfDay(),
            $result->start->toMicroOfDay()
        );
    }

    public function test_reduce_empty_set_returns_initial_value(): void
    {
        $set = new IntervalSet();

        $result = $set->reduce(
            fn (int $carry, Interval $i) => $carry + 1,
            42
        );

        self::assertSame(42, $result);
    }

    public function test_formatted_strings(): void
    {
        $set = new IntervalSet(
            $this->i(1, 2),
            $this->i(3, 4),
        );

        self::assertSame(
            [
                $this->i(1, 2)->format(),
                $this->i(3, 4)->format(),
            ],
            $set->allFormatted()
        );
    }
}
