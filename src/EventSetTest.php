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
        $result = $set->inside($interval);

        self::assertGreaterThanOrEqual(2, $result->count());
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
        self::assertSame('B', $result->identifiers->first());
    }

    public function test_next_includes_exact_time(): void
    {
        $result = $this
            ->basicEventSet()
            ->next(Time::at(12, 00), SearchMode::Linear)
            ->first();

        self::assertInstanceOf(Event::class, $result);
        self::assertSame('B', $result->identifiers->first());
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
        self::assertSame('B', $result->identifiers->first());
    }

    public function test_previous_excludes_exact_time(): void
    {
        $result = $this
            ->basicEventSet()
            ->previous(Time::at(12, 00), SearchMode::Circular)
            ->first();

        self::assertInstanceOf(Event::class, $result);
        self::assertSame('B', $result->identifiers->first());
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
        self::assertSame('B', $result->identifiers->first());
    }

    public function test_nearest_prefers_forward_when_tie(): void
    {
        $results = $this
            ->basicEventSet()
            ->nearest(Time::at(10, 30));
        self::assertCount(2, $results);

        $firstResult = $results->first();
        self::assertInstanceOf(Event::class, $firstResult);
        self::assertSame('A', $firstResult->identifiers->first());
    }

    public function test_nearest_exact_match(): void
    {
        $result = $this
            ->basicEventSet()
            ->nearest(Time::at(12, 00))
            ->first();

        self::assertInstanceOf(Event::class, $result);
        self::assertSame('B', $result->identifiers->first());
    }

    public function test_next_and_previous_do_not_overlap(): void
    {
        $t = Time::at(12, 00);

        $events = $this->basicEventSet();
        $prev = $events->previous($t, SearchMode::Linear);
        $next = $events->next($t, SearchMode::Linear);

        self::assertNotSame(
            $prev->first()?->identifiers->first(),
            $next->first()?->identifiers->first(),
        );
    }

    public function test_duration_can_be_serialized_and_unserialized(): void
    {
        $eventSet = $this->basicEventSet();
        $restored = unserialize(serialize($eventSet));

        self::assertInstanceOf(EventSet::class, $restored);
        self::assertEquals($eventSet, $restored);
    }
}
