<?php

declare(strict_types=1);

namespace Bakame\Tokei;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ValueError;

use function array_map;
use function iterator_to_array;
use function json_encode;
use function serialize;
use function unserialize;

use const JSON_UNESCAPED_SLASHES;

#[CoversClass(IntervalFormat::class)]
#[CoversClass(DurationFormat::class)]
#[CoversClass(IntervalSet::class)]
#[CoversClass(Interval::class)]
final class IntervalSetTest extends TestCase
{
    public function test_it_can_be_empty(): void
    {
        $set = new IntervalSet();

        self::assertTrue($set->isEmpty());
        self::assertCount(0, $set);
        self::assertNull($set->first());
        self::assertNull($set->last());
        self::assertTrue(Duration::zero()->equals($set->duration()));
    }

    public function test_it_preserves_order(): void
    {
        $a = Interval::between(Time::at(10), Time::at(11));
        $b = Interval::between(Time::at(12), Time::at(13));
        $notFound = Interval::between(Time::at(14), Time::at(15));
        $set = new IntervalSet($a, $b);

        self::assertSame($a, $set->first());
        self::assertSame($b, $set->last());
        self::assertFalse(Duration::zero()->equals($set->duration()));
        self::assertTrue($set->has($a));
        self::assertTrue($set->has($a, $b));
        self::assertFalse($set->has($a, $b, $notFound));
    }

    public function test_get_supports_negative_offsets(): void
    {
        $a = Interval::between(Time::at(10), Time::at(11));
        $b = Interval::between(Time::at(12), Time::at(13));

        $set = new IntervalSet($a, $b);

        self::assertSame($b, $set->nth(-1));
        self::assertSame($b, $set->get(-1));
        self::assertSame($a, $set->nth(-2));
        self::assertSame($a, $set->get(-2));
        self::assertNull($set->nth(-3));

        $this->expectExceptionObject(new TimeException('Invalid offset (-3) given to '.IntervalSet::class.'.'));
        $set->get(-3);
    }

    public function test_push_returns_same_instance_when_empty(): void
    {
        $set = new IntervalSet();

        self::assertSame($set, $set->push());
        self::assertSame($set, $set->unshift());
    }

    public function test_push_appends_intervals(): void
    {
        $a = Interval::between(Time::at(10), Time::at(11));
        $b = Interval::between(Time::at(12), Time::at(13));

        $setP = (new IntervalSet($a))->push($b);

        self::assertCount(2, $setP);
        self::assertSame($a, $setP->first());
        self::assertSame($b, $setP->last());

        $setU = (new IntervalSet($a))->unshift($b);
        self::assertCount(2, $setU);
        self::assertSame($a, $setU->last());
        self::assertSame($b, $setU->first());
    }

    public function test_matching_methods(): void
    {
        $a = Interval::between(Time::at(10), Time::at(13));
        $b = Interval::between(Time::at(12), Time::at(13));
        $time = Time::noon();
        $set = new IntervalSet($a, $b);

        $callback = static fn (Interval $interval): bool => $interval->includes($time);
        $callbackNotFound = static fn (Interval $interval): bool => $interval->includes(Time::midnight());

        self::assertSame($a, $set->firstMatching($callback));
        self::assertSame($b, $set->lastMatching($callback));
        self::assertNull($set->firstMatching($callbackNotFound));
        self::assertNull($set->lastMatching($callbackNotFound));
    }

    public function test_remove_elements(): void
    {
        $a = Interval::between(Time::at(10), Time::at(13));
        $b = Interval::between(Time::at(12), Time::at(13));
        $set = new IntervalSet($a, $b);

        self::assertSame($set->remove(3), $set);
        self::assertSame($set->remove(), $set);

        $resRemoveAll = $set->remove(0, 1);
        self::assertTrue($resRemoveAll->isEmpty());

        $resRemoveOne = $set->remove(1);
        self::assertCount(1, $resRemoveOne);
        self::assertEquals($a, $resRemoveOne->first());

        $resRemoveOne = $set->remove(-1);
        self::assertCount(1, $resRemoveOne);
        self::assertEquals($a, $resRemoveOne->first());
    }

    public function test_replace_elements(): void
    {
        $a = Interval::between(Time::at(10), Time::at(13));
        $b = Interval::between(Time::at(12), Time::at(13));
        $set = new IntervalSet($a, $b);
        $c = Interval::between(Time::at(14), Time::at(15));

        $replaceNeg = $set->replace(-1, $c);
        self::assertCount(2, $replaceNeg);
        self::assertSame($c, $replaceNeg->last());
        self::assertFalse($replaceNeg->has($b));

        $this->expectExceptionObject(new TimeException('Invalid offset (3) given to '.IntervalSet::class.'.'));
        $set->replace(3, $c);

    }

    public function test_set_can_be_serialized_and_unserialized(): void
    {
        $a = Interval::between(Time::at(10), Time::at(13));
        $b = Interval::between(Time::at(12), Time::at(13));
        $set = new IntervalSet($a, $b);

        $restored = unserialize(serialize($set));

        self::assertInstanceOf(IntervalSet::class, $restored);
        self::assertEquals($set, $restored);
    }

    public function test_normalize_sorts_intervals(): void
    {
        $a = Interval::between(Time::at(12), Time::at(14));
        $b = Interval::between(Time::at(10), Time::at(11));

        $normalized = (new IntervalSet($a, $b))->union();
        $normalizedBis = $a->union($b);

        self::assertTrue($normalized->first()?->equals($b));
        self::assertTrue($normalized->last()?->equals($a));
        self::assertEquals($normalizedBis, $normalized);
    }

    public function test_union_with_arguments(): void
    {
        $emptySet = new IntervalSet();
        $interval = Interval::between(Time::at(12), Time::at(14));
        $expectedSet = new IntervalSet($interval);

        self::assertEquals($emptySet, $emptySet->union());
        self::assertEquals($emptySet, $emptySet->union($emptySet));
        self::assertEquals($expectedSet, $emptySet->union($interval));
        self::assertEquals($expectedSet, $expectedSet->union($emptySet));
        self::assertEquals($expectedSet, $expectedSet->union($interval));
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
        $b = Interval::between(Time::at(10)->shift(Duration::of(hours: 10)), Time::at(20)->shift(Duration::of(hours: 10)));

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

        $second = $result->nth(1);
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
        $b = Interval::between(Time::at(15), Time::at(23)->shift(Duration::of(hours: 2)));

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
        $b = Interval::between(Time::at(5), Time::at(2)->shift(Duration::of(hours: 1)));

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

        $result = (new IntervalSet($a))->difference();

        self::assertCount(1, $result);
        $first = $result->first();
        self::assertInstanceOf(Interval::class, $first);
        self::assertTrue($a->equals($first));
    }

    public function test_difference_handles_circular_intervals(): void
    {
        $a = Interval::between(Time::at(22), Time::at(4));
        $b = Interval::between(Time::at(23), Time::at(1));

        $result = (new IntervalSet($a))->difference($b);

        self::assertCount(2, $result);
        $first = $result->first();
        self::assertInstanceOf(Interval::class, $first);
        self::assertTrue(Interval::between(Time::at(22), Time::at(23))->equals($first));

        $second = $result->nth(1);
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
                fn (Interval $i) => $i->start->toOffset(Unit::Microsecond),
                $set->all()
            ),
            array_map(
                fn (Interval $i) => $i->start->toOffset(Unit::Microsecond),
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
                $i->start->toOffset(Unit::Microsecond) >= Time::at(3)->toOffset(Unit::Microsecond)
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
            $this->i(10, 20)->start->toOffset(Unit::Microsecond),
            $result->start->toOffset(Unit::Microsecond)
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
        $set = new IntervalSet($this->i(1, 2), $this->i(3, 4));

        self::assertSame([$this->i(1, 2)->format(IntervalFormat::Iso80000), $this->i(3, 4)->format(IntervalFormat::Iso80000)], $set->allFormatted(IntervalFormat::Iso80000));
        self::assertSame([$this->i(1, 2)->format(), $this->i(3, 4)->format()], $set->allFormatted());
        self::assertSame([$this->i(1, 2)->format(IntervalFormat::Bourbaki), $this->i(3, 4)->format(IntervalFormat::Bourbaki)], $set->allFormatted(IntervalFormat::Bourbaki));
    }

    public function test_json_encoded_set(): void
    {
        self::assertStringContainsString('"12:00:00/PT6H"', (string) json_encode(Business::shifts(), JSON_UNESCAPED_SLASHES));
    }

    public function test_native_conversion(): void
    {
        $class = new class () extends DateTimeImmutable {};
        $timeZoneName = 'Africa/Brazzaville';

        $converted = Business::shifts()->allNative(new $class('2025-03-02 23:12:59', new DateTimeZone($timeZoneName)));
        self::assertCount(5, $converted);

        $interval = $converted[1];
        self::assertInstanceOf($class::class, $interval['startDate']);
        self::assertSame($interval['startDate']->getTimezone()->getName(), $timeZoneName);
        self::assertSame('2025-03-02 12:00:00', $interval['startDate']->format('Y-m-d H:i:s'));
        self::assertEquals(new DateInterval('PT6H'), $interval['interval']);
    }

    public function test_it_can_be_iterated_with_foreach(): void
    {
        $set = new IntervalSet(
            Interval::between(Time::at(hour: 10), Time::at(hour: 11)),
            Interval::between(Time::noon(), Time::at(hour: 13)),
        );

        $results = [];
        foreach ($set as $item) {
            $results[] = $item;
        }

        self::assertCount(2, $results);
        self::assertSame($set->first(), $results[0]);
        self::assertSame($set->last(), $results[1]);
    }

    public function test_union_on_empty_set(): void
    {
        self::assertTrue((new IntervalSet())->union()->isEmpty());
    }

    public function test_difference_on_empty_set(): void
    {
        self::assertTrue((new IntervalSet())->difference()->isEmpty());
        self::assertEquals(Business::shifts(), Business::shifts()->difference());
        self::assertTrue((new IntervalSet())->difference(Business::shifts())->isEmpty());
    }

    public function test_sorted_on_empty_set(): void
    {
        $set = new IntervalSet();
        self::assertSame($set, $set->sorted());
    }

    public function test_sorted_on_an_already_sorted_set(): void
    {
        $set = new IntervalSet(
            Interval::between(Time::at(hour: 10), Time::at(hour: 13)),
            Interval::between(Time::noon(), Time::at(hour: 13)),
        );

        self::assertSame($set, $set->sorted());
    }

    public function test_sorting_a_set_returns_a_new_set(): void
    {
        $set = new IntervalSet(
            Interval::between(Time::noon(), Time::at(hour: 13)),
            Interval::between(Time::at(hour: 10), Time::at(hour: 13)),
        );
        $sorted = $set->sorted();

        self::assertNotSame($set, $sorted);
        self::assertSame($set->last(), $sorted->first());
    }

    public function test_sorting_a_set_descending(): void
    {
        $set = new IntervalSet(
            Interval::between(Time::at(hour: 10), Time::at(hour: 13)),
            Interval::between(Time::noon(), Time::at(hour: 13)),
        );
        $sorted = $set->sorted(sortDirection: 'descending');

        self::assertNotSame($set, $sorted);
        self::assertSame($set->last(), $sorted->first());
    }

    public function test_sorting_a_set_descending_with_the_end_boundary(): void
    {
        $set = new IntervalSet(
            Interval::between(Time::at(hour: 10), Time::at(hour: 13)),
            Interval::between(Time::noon(), Time::at(hour: 13)),
        );
        $sorted = $set->sorted(sortDirection: 'descending', sortBound: Bound::End);

        self::assertNotSame($set, $sorted);
        self::assertSame($set->last(), $sorted->first());
    }

    public function test_transform(): void
    {
        $duration = Duration::of(hours: 1);
        $set = new IntervalSet(
            Interval::between(Time::at(hour: 10), Time::at(hour: 13)),
            Interval::between(Time::noon(), Time::at(hour: 13)),
        );
        $res = $set->transform(fn (Interval $interval) => $interval->lasting($duration, Bound::End));

        self::assertNotEquals($set, $res);
        self::assertCount(2, $res);
        self::assertTrue($res->every(fn (Interval $interval) => $interval->duration->equals($duration)));
    }

    public function test_sorting_with_an_invalid_sorting_direction(): void
    {
        $this->expectException(ValueError::class);

        (new IntervalSet())->sorted(Bound::Start, 'foo');
    }

    /**
     * @throws InvalidDuration
     */
    public function testComplementOfEmptySetIsFullDay(): void
    {
        $set = new IntervalSet();

        self::assertEquals(new IntervalSet(Interval::fullDay()), $set->complement());
    }

    /**
     * @throws InvalidDuration
     */
    public function testComplementOfFullDayIsEmpty(): void
    {
        $set = new IntervalSet(Interval::fullDay());

        self::assertTrue($set->complement()->isEmpty());
    }

    public function testComplementSingleInterval(): void
    {
        $set = new IntervalSet(Interval::between(Time::at(10), Time::noon()));
        $expected = new IntervalSet(
            Interval::between(Time::midnight(), Time::at(10)),
            Interval::between(Time::noon(), Time::midnight()),
        );

        self::assertEquals($expected->allFormatted(), $set->complement()->allFormatted());
    }

    public function testComplementIntervalAtStart(): void
    {
        $set = new IntervalSet(Interval::between(Time::midnight(), Time::at(3)));
        $expected = new IntervalSet(Interval::between(Time::at(3, 0), Time::midnight()));

        self::assertEquals($expected->allFormatted(), $set->complement()->allFormatted());
    }

    public function testComplementIntervalAtEnd(): void
    {
        $set = new IntervalSet(Interval::between(Time::at(22, 0), Time::midnight()));
        $expected = new IntervalSet(Interval::between(Time::midnight(), Time::at(22)));

        self::assertEquals($expected->allFormatted(), $set->complement()->allFormatted());
    }

    public function testComplementMultipleIntervals(): void
    {
        $set = new IntervalSet(
            Interval::between(Time::at(2), Time::at(4)),
            Interval::between(Time::at(10), Time::noon()),
        );

        $expected = new IntervalSet(
            Interval::between(Time::midnight(), Time::at(2)),
            Interval::between(Time::at(4), Time::at(10)),
            Interval::between(Time::noon(), Time::midnight()),
        );

        self::assertEquals($expected->allFormatted(), $set->complement()->allFormatted());
    }

    public function testComplementOfCircularInterval(): void
    {
        $set = new IntervalSet(Interval::fullDay());

        self::assertTrue($set->complement()->isEmpty());
    }

    public function testComplementIsInvolutive(): void
    {
        $set = new IntervalSet(Interval::between(Time::at(3), Time::at(7)));

        self::assertEquals($set->allFormatted(), $set->complement()->complement()->allFormatted());
    }

    public function test_intersect_returns_itself_with_empty_intervals(): void
    {
        $set = new IntervalSet();
        self::assertSame($set, $set->intersect(new IntervalSet()));
        self::assertSame($set, $set->intersect());

        $setBis = new IntervalSet(Interval::between(Time::at(3), Time::at(7)));
        self::assertSame($setBis, $setBis->intersect());
        self::assertSame($setBis, $setBis->intersect(new IntervalSet()));
    }

    public function test_indexOf(): void
    {
        $set = new IntervalSet();
        $notFound = Interval::between(Time::at(3), Time::at(7));
        self::assertNull($set->indexOf($notFound));
        self::assertNull($set->lastIndexOf($notFound));

        $expected = new IntervalSet(
            $found = Interval::between(Time::midnight(), Time::at(2)),
            Interval::between(Time::at(4), Time::at(10)),
            $found,
            Interval::between(Time::noon(), Time::midnight()),
        );

        self::assertSame(0, $expected->indexOf($found));
        self::assertSame(2, $expected->lastIndexOf($found));
        self::assertNull($expected->indexOf($notFound));
        self::assertNull($expected->lastIndexOf($notFound));
    }

    public function test_any(): void
    {
        $set = new IntervalSet(
            Interval::between(Time::midnight(), Time::at(2)),
            Interval::between(Time::at(4), Time::at(10)),
            Interval::between(Time::noon(), Time::midnight()),
        );

        self::assertTrue($set->any(fn (Interval $interval): bool => $interval->includes(Time::at(6))));
        self::assertFalse($set->any(fn (Interval $interval, ?int $offset = null): bool => $interval->includes(Time::at(6)) && 0 === $offset));
    }

    public function test_every(): void
    {
        $set = new IntervalSet(
            Interval::between(Time::midnight(), Time::at(2)),
            Interval::between(Time::at(4), Time::at(10)),
            Interval::between(Time::noon(), Time::midnight()),
        );

        self::assertFalse($set->every(fn (Interval $interval): bool => $interval->includes(Time::at(6))));
        self::assertTrue($set->every(fn (Interval $interval, ?int $offset = null): bool => true));
    }

    public function test_differences_are_the_same_independant_of_circular_interval(): void
    {
        $intervalSet = new IntervalSet(Interval::between(Time::midnight(), Time::noon()));

        $diff1 = (new IntervalSet(Interval::circular(Time::at(hour: 10))))
            ->difference($intervalSet)
            ->allFormatted();

        $diff2 = (new IntervalSet(Interval::fullDay()))
            ->difference($intervalSet)
            ->allFormatted();

        self::assertEquals($diff1, $diff2);
    }

    public function testItIteratesOverAllIntervals(): void
    {
        $intervals = new IntervalSet(
            Interval::between(Time::at(1), Time::at(2)),
            Interval::between(Time::at(3), Time::at(4)),
            Interval::between(Time::at(5), Time::at(6)),
        );

        $visited = [];

        $result = $intervals->each(
            function (Interval $interval, ?int $index = null) use (&$visited): void {
                $visited[] = $index;
            }
        );

        self::assertTrue($result);
        self::assertSame([0, 1, 2], $visited);
    }

    public function testItStopsIterationWhenCallbackReturnsFalse(): void
    {
        $intervals = new IntervalSet(
            Interval::between(Time::at(1), Time::at(2)),
            Interval::between(Time::at(3), Time::at(4)),
            Interval::between(Time::at(5), Time::at(6)),
        );

        $visited = [];

        $result = $intervals->each(
            function (Interval $interval, ?int $index = null) use (&$visited): bool {
                $visited[] = $index;

                return 1 !== $index;
            }
        );

        self::assertFalse($result);
        self::assertSame([0, 1], $visited);
    }

    public function testItReturnsTrueOnEmptyCollection(): void
    {
        $intervals = new IntervalSet();

        $visited = false;

        $result = $intervals->each(
            function () use (&$visited): void {
                $visited = true;
            }
        );

        self::assertTrue($result);
        self::assertFalse($visited);
    }

    public function test_gaps_with_empty_collection(): void
    {
        $intervals = new IntervalSet();

        self::assertSame($intervals, $intervals->gaps());
    }
}

enum Business
{
    case Morning;
    case Afternoon;
    case Evening;
    case Night;
    case Day;

    /**
     * @throws InvalidDuration|InvalidTime
     */
    public function interval(): Interval
    {
        return match ($this) {
            self::Morning => Interval::between(Time::at(6), Time::noon()),
            self::Afternoon => Interval::between(Time::noon(), Time::at(18)),
            self::Evening => Interval::between(Time::at(18), Time::at(22)),
            self::Night => Interval::between(Time::at(22), Time::at(6)),
            self::Day => Interval::between(Time::at(6), Time::at(22)),
        };
    }

    /**
     * @throws InvalidDuration|InvalidTime
     */
    public static function shifts(): IntervalSet
    {
        return new IntervalSet(...array_map(static fn (self $case): Interval => $case->interval(), self::cases()));
    }
}
