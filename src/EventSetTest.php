<?php

declare(strict_types=1);

namespace Bakame\Tokei;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function serialize;
use function unserialize;

#[CoversClass(EventSet::class)]
#[CoversClass(Event::class)]
#[CoversClass(TemporalSearch::class)]
#[CoversClass(TokeiException::class)]
#[CoversClass(Identifiers::class)]
final class EventSetTest extends TestCase
{
    /* =========================================================
     * Helpers
     * ========================================================= */

    private function event(int $hour, int $minute = 0): Event
    {
        return Event::at(Time::at($hour, $minute));
    }

    /* =========================================================
     * STRUCTURAL INVARIANTS
     * ========================================================= */

    public function test_it_is_always_sorted_chronologically(): void
    {
        $set = new EventSet(
            $this->event(23),
            $this->event(1),
            $this->event(12),
        );

        $events = $set->all();

        self::assertSame(1, $events[0]->at->hour);
        self::assertSame(12, $events[1]->at->hour);
        self::assertSame(23, $events[2]->at->hour);
    }

    public function test_count_returns_number_of_events(): void
    {
        $set = new EventSet($this->event(10), $this->event(11));

        self::assertCount(2, $set);
    }

    public function test_first_and_last(): void
    {
        $set = new EventSet(
            $this->event(10),
            $this->event(20),
        );

        self::assertInstanceOf(Event::class, $set->first());
        self::assertSame(10, $set->first()->at->hour);
        self::assertInstanceOf(Event::class, $set->last());
        self::assertSame(20, $set->last()->at->hour);
    }

    public function test_nth_and_negative_indexing(): void
    {
        $set = new EventSet(
            $this->event(10),
            $this->event(20),
            $this->event(23),
        );

        self::assertInstanceOf(Event::class, $set->nth(1));
        self::assertSame(20, $set->nth(1)->at->hour);
        self::assertInstanceOf(Event::class, $set->nth(-1));
        self::assertSame(23, $set->nth(-1)->at->hour);
        self::assertInstanceOf(Event::class, $set->nth(-3));
        self::assertSame(10, $set->nth(-3)->at->hour);
    }

    public function test_get_throws_on_invalid_offset(): void
    {
        $this->expectExceptionObject(TokeiException::dueToInvalidOffset(5, EventSet::class));

        $set = new EventSet($this->event(10));

        $set->get(5);
    }

    public function test_push_is_immutable(): void
    {
        $set = new EventSet($this->event(10));
        $new = $set->push($this->event(20));

        self::assertCount(1, $set);
        self::assertCount(2, $new);
    }

    public function test_iterator_returns_events(): void
    {
        $set = new EventSet(
            $this->event(10),
            $this->event(20),
        );

        $hours = [];
        foreach ($set as $event) {
            $hours[] = $event->at->hour;
        }

        self::assertSame([10, 20], $hours);
    }

    /* =========================================================
     * INDEXING BEHAVIOR
     * ========================================================= */

    public function test_index_of_returns_position(): void
    {
        $e1 = $this->event(10);
        $e2 = $this->event(20);

        $set = new EventSet($e1, $e2);

        self::assertSame(1, $set->indexOf($e2));
        self::assertSame(0, $set->indexOf($e1));
    }

    public function test_last_index_of_returns_last_match(): void
    {
        $event = $this->event(10);

        $set = new EventSet($event, $this->event(20), $event);

        self::assertSame(1, $set->lastIndexOf($event));
    }

    /* =========================================================
     * FILTERING BEHAVIOR
     * ========================================================= */

    public function test_at_filters_by_exact_time(): void
    {
        $set = new EventSet($target = $this->event(10), $this->event(20));
        $result = $set->at($target->at);

        self::assertCount(1, $result);
        self::assertInstanceOf(Event::class, $result->first());
        self::assertSame(10, $result->first()->at->hour);
    }

    public function test_between_filters_events_in_interval(): void
    {
        $set = new EventSet(
            $this->event(23),
            $this->event(0),
            $this->event(1),
            $this->event(5),
        );

        $interval = Interval::between(Time::at(23), Time::at(2));

        self::assertCount(3, $set->inside($interval));
        self::assertCount(1, $set->outside($interval));
        self::assertCount(3, $set->before(Time::at(23)));
        self::assertCount(1, $set->after(Time::at(10)));
    }

    /* =========================================================
     * CIRCULAR NAVIGATION
     * ========================================================= */

    public function test_next_wraps_across_midnight(): void
    {
        $set = new EventSet(
            $this->event(23, 59),
            $this->event(0, 1),
        );

        $result = $set->next(Time::at(23, 58), SearchMode::Circular);
        self::assertInstanceOf(Event::class, $result->first());
        self::assertSame(23, $result->first()->at->hour);
        self::assertSame(59, $result->first()->at->minute);
    }

    public function test_previous_wraps_across_midnight(): void
    {
        $set = new EventSet(
            $this->event(0, 1),
            $this->event(23, 59),
        );

        $result = $set->previous(Time::at(0, 0), SearchMode::Circular);
        self::assertInstanceOf(Event::class, $result->first());
        self::assertSame(23, $result->first()->at->hour);
        self::assertSame(59, $result->first()->at->minute);
    }

    public function test_nearest_selects_closest_event(): void
    {
        $set = new EventSet(
            $this->event(0, 1),
            $this->event(23, 59),
        );

        $result = $set->nearest(Time::at(0, 0));

        self::assertFalse($result->isEmpty());
    }

    public function test_nearest_handles_tie_deterministically(): void
    {
        $set = new EventSet(
            $this->event(0, 1),
            $this->event(23, 59),
        );

        $result = $set->nearest(Time::at(0, 0));

        self::assertInstanceOf(Event::class, $result->first());
        self::assertContains(
            $result->first()->at->format(),
            [
                Time::at(0, 1)->format(),
                Time::at(23, 59)->format(),
            ]
        );
    }

    /* =========================================================
     * IMMUTABILITY + CONSISTENCY
     * ========================================================= */

    public function test_push_keeps_chronological_order(): void
    {
        $set = new EventSet($this->event(10));

        $set = $set->push($this->event(5));
        self::assertInstanceOf(Event::class, $set->first());
        self::assertSame(5, $set->first()->at->hour);
        self::assertInstanceOf(Event::class, $set->last());
        self::assertSame(10, $set->last()->at->hour);
    }

    /* =========================================================
     * POSITIONAL API
     * ========================================================= */

    private function basicEventSet(): EventSet
    {
        return new EventSet(
            Event::at(Time::at(9, 00), 'A'),
            Event::at(Time::at(12, 00), 'B'),
            Event::at(Time::at(15, 00), 'C'),
        );
    }

    public function test_next_returns_next_event(): void
    {
        $result = $this
            ->basicEventSet()
            ->next(Time::at(10, 00), SearchMode::Circular)
            ->first();

        self::assertInstanceOf(Event::class, $result);
        self::assertSame('B', $result->identifiers->primary());
    }

    public function test_next_includes_exact_time(): void
    {
        $result = $this
            ->basicEventSet()
            ->next(Time::at(12, 00), SearchMode::Linear)
            ->first();

        self::assertInstanceOf(Event::class, $result);
        self::assertSame('B', $result->identifiers->primary());
    }

    public function test_next_returns_null_if_none(): void
    {
        $result = $this
            ->basicEventSet()
            ->next(Time::at(16, 00), SearchMode::Linear);

        self::assertTrue($result->isEmpty());
    }

    public function test_previous_returns_previous_event(): void
    {
        $result = $this
            ->basicEventSet()
            ->previous(Time::at(13, 00), SearchMode::Circular)
            ->first();

        self::assertInstanceOf(Event::class, $result);
        self::assertSame('B', $result->identifiers->primary());
    }

    public function test_previous_excludes_exact_time(): void
    {
        $result = $this
            ->basicEventSet()
            ->previous(Time::at(12, 00), SearchMode::Circular)
            ->first();

        self::assertInstanceOf(Event::class, $result);
        self::assertSame('B', $result->identifiers->primary());
    }

    public function test_previous_returns_null_when_none(): void
    {
        $result = $this
            ->basicEventSet()
            ->previous(Time::at(8, 00), SearchMode::Linear);

        self::assertTrue($result->isEmpty());
    }

    public function test_nearest_returns_closest_event(): void
    {
        $result = $this
            ->basicEventSet()
            ->nearest(Time::at(11, 00))
            ->first();

        self::assertInstanceOf(Event::class, $result);
        self::assertSame('B', $result->identifiers->primary());
    }

    public function test_nearest_prefers_forward_when_tie(): void
    {
        $results = $this
            ->basicEventSet()
            ->nearest(Time::at(10, 30));
        self::assertCount(2, $results);

        $firstResult = $results->first();
        self::assertInstanceOf(Event::class, $firstResult);
        self::assertSame('A', $firstResult->identifiers->primary());
    }

    public function test_nearest_exact_match(): void
    {
        $result = $this
            ->basicEventSet()
            ->nearest(Time::at(12, 00))
            ->first();

        self::assertInstanceOf(Event::class, $result);
        self::assertSame('B', $result->identifiers->primary());
    }

    public function test_next_and_previous_do_not_overlap(): void
    {
        $t = Time::at(12, 00);

        $events = $this->basicEventSet();
        $prev = $events->previous($t, SearchMode::Linear);
        $next = $events->next($t, SearchMode::Linear);

        self::assertNotSame(
            $prev->first()?->identifiers->primary(),
            $next->first()?->identifiers->primary(),
        );
    }

    public function test_duration_can_be_serialized_and_unserialized(): void
    {
        $eventSet = $this->basicEventSet();
        $restored = unserialize(serialize($eventSet));

        self::assertInstanceOf(EventSet::class, $restored);
        self::assertEquals($eventSet, $restored);
    }

    public function testUnionOfDisjointSets(): void
    {
        $left = new EventSet(Event::at(Time::at(9), 'A'));
        $right = new EventSet(Event::at(Time::at(10), 'B'));

        self::assertSame(['09:00:00;A', '10:00:00;B'], $left->union($right)->formatAll());
    }

    public function testUnionMergesEventsAtSameTime(): void
    {
        $left = new EventSet(Event::at(Time::at(9), 'A'));
        $right = new EventSet(Event::at(Time::at(9), 'B'));

        self::assertSame(['09:00:00;A,B'], $left->union($right)->formatAll());
    }

    public function testUnionWithPartialOverlap(): void
    {
        $left = new EventSet(
            Event::at(Time::at(9), 'A'),
            Event::at(Time::at(10), 'B')
        );

        $right = new EventSet(
            Event::at(Time::at(10), 'C'),
            Event::at(Time::at(11), 'D')
        );

        self::assertSame(
            [
                '09:00:00;A',
                '10:00:00;B,C',
                '11:00:00;D',
            ],
            $left->union($right)->formatAll()
        );
    }

    public function testIntersectOfDisjointSetsIsEmpty(): void
    {
        $left = new EventSet(Event::at(Time::at(9), 'A'));
        $right = new EventSet(Event::at(Time::at(10), 'B'));

        self::assertTrue($left->intersect($right)->isEmpty());
    }

    public function testIntersectKeepsCommonEvent(): void
    {
        $left = new EventSet(Event::at(Time::at(9), 'A'));
        $right = new EventSet(Event::at(Time::at(9), 'B'));

        self::assertSame(['09:00:00;A,B'], $left->intersect($right)->formatAll());
    }

    public function testIntersectKeepsOnlySharedTimes(): void
    {
        $left = new EventSet(
            Event::at(Time::at(9), 'A'),
            Event::at(Time::at(10), 'B'),
            Event::at(Time::at(11), 'C')
        );

        $right = new EventSet(
            Event::at(Time::at(10), 'D'),
            Event::at(Time::at(11), 'E'),
            Event::at(Time::at(12), 'F')
        );

        self::assertSame(
            [
                '10:00:00;B,D',
                '11:00:00;C,E',
            ],
            $left->intersect($right)->formatAll()
        );
    }

    public function testDifferenceOfDisjointSetsReturnsOriginalSet(): void
    {
        $left = new EventSet(Event::at(Time::at(9), 'A'));
        $right = new EventSet(Event::at(Time::at(10), 'B'));

        self::assertSame(['09:00:00;A'], $left->difference($right)->formatAll());
    }

    public function testDifferenceRemovesCommonEvent(): void
    {
        $left = new EventSet(
            Event::at(Time::at(9), 'A'),
            Event::at(Time::at(10), 'B')
        );
        $right = new EventSet(Event::at(Time::at(10), 'C'));

        self::assertSame(['09:00:00;A'], $left->difference($right)->formatAll());
    }

    public function testDifferenceOfIdenticalSetsIsEmpty(): void
    {
        $left = new EventSet(Event::at(Time::at(9), 'A'));
        $right = new EventSet(Event::at(Time::at(9), 'B'));

        self::assertTrue($left->difference($right)->isEmpty());
    }

    public function testUnionIsCommutative(): void
    {
        $left = new EventSet(Event::at(Time::at(9), 'A'));
        $right = new EventSet(Event::at(Time::at(9), 'B'));

        $leftFirst = $left->union($right)->first();
        self::assertInstanceOf(Event::class, $leftFirst);

        $rightFirst = $right->union($left)->first();
        self::assertInstanceOf(Event::class, $rightFirst);

        self::assertTrue($leftFirst->identifiers->equals($rightFirst->identifiers));
    }

    public function testIntersectionIsCommutative(): void
    {
        $left = new EventSet(
            Event::at(Time::at(9), 'A'),
            Event::at(Time::at(10), 'B'),
            Event::at(Time::at(11), 'C')
        );

        $right = new EventSet(
            Event::at(Time::at(10), 'D'),
            Event::at(Time::at(11), 'E'),
            Event::at(Time::at(12), 'F')
        );

        $leftFirst = $left->intersect($right)->first();
        self::assertInstanceOf(Event::class, $leftFirst);

        $rightFirst = $right->intersect($left)->first();
        self::assertInstanceOf(Event::class, $rightFirst);

        self::assertTrue($leftFirst->identifiers->equals($rightFirst->identifiers));
    }

    public function testDifferenceIsNotCommutative(): void
    {
        $left = new EventSet(
            Event::at(Time::at(9), 'A'),
            Event::at(Time::at(10), 'B')
        );
        $right = new EventSet(Event::at(Time::at(10), 'C'));

        self::assertNotEquals(
            $left->difference($right),
            $right->difference($left)
        );
    }

    public function testIntersectionIsSubsetOfUnion(): void
    {
        $left = new EventSet(
            Event::at(Time::at(9), 'A'),
            Event::at(Time::at(10), 'B'),
            Event::at(Time::at(11), 'C')
        );

        $right = new EventSet(
            Event::at(Time::at(10), 'D'),
            Event::at(Time::at(11), 'E'),
            Event::at(Time::at(12), 'F')
        );

        $intersection = $left->intersect($right);
        $union = $left->union($right);

        foreach ($intersection as $event) {
            self::assertTrue($union->has($event));
        }
    }

    public function testGapsUnavailable(): void
    {
        $left = new EventSet()->gaps();
        $right = new EventSet(Event::at(Time::at(9), 'B'))->gaps();

        self::assertTrue($left->isEmpty());
        self::assertTrue($right->isEmpty());
    }

    public function testGapsForEvents(): void
    {
        $gaps = new EventSet(
            Event::at(Time::at(9), 'A'),
            Event::at(Time::at(10), 'B'),
            Event::at(Time::at(11), 'C')
        )->gaps();

        self::assertFalse($gaps->isEmpty());
        self::assertCount(2, $gaps);
        self::assertInstanceOf(Interval::class, $gaps->first());
        self::assertInstanceOf(Interval::class, $gaps->last());
        self::assertTrue($gaps->first()->equals(Interval::between(Time::at(9), Time::at(10))));
        self::assertTrue($gaps->last()->equals(Interval::between(Time::at(10), Time::at(11))));
    }

    public function test_shift_set(): void
    {
        $shifts = new EventSet(
            Event::at(Time::at(9), 'A'),
            Event::at(Time::at(10), 'B'),
            Event::at(Time::at(11), 'C')
        )->shift(Duration::of(hours: 2));

        self::assertFalse($shifts->isEmpty());
        self::assertCount(3, $shifts);
        self::assertTrue(
            $shifts->get(0)->equals(
                Event::at(Time::at(11), 'A')
            )
        );
    }

    public function test_roundto_set(): void
    {
        $events = new EventSet(
            Event::at(Time::at(9, 23), 'A'),
            Event::at(Time::at(10, 35), 'B'),
            Event::at(Time::at(11, 22), 'C')
        );

        $roundedEvents = $events->roundTo(Unit::Hour, SnapMode::Floor);

        self::assertFalse($roundedEvents->isEmpty());
        self::assertCount(3, $roundedEvents);
        self::assertTrue(new Identifiers(...$events)->equals(new Identifiers(...$roundedEvents)));
        self::assertTrue(Time::at(9)->equals($roundedEvents->get(0)->at));
    }
}
