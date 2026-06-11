<?php

declare(strict_types=1);

namespace Bakame\Tokei;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function serialize;
use function unserialize;

#[CoversClass(Task::class)]
#[CoversClass(TaskSet::class)]
#[CoversClass(IntervalSet::class)]
#[CoversClass(TemporalSearch::class)]
final class TaskSetTest extends TestCase
{
    /**
     * @param non-empty-string $identifiers
     */
    private function task(string $identifiers, Interval $interval): Task
    {
        return Task::for($interval, $identifiers);
    }

    /**
     * @return array<non-falsy-string>
     */
    private function format(TaskSet $set): array
    {
        return iterator_to_array(
            $set->map(
                fn (Task $task): string => implode(' => ', [
                    $task->period->format(IntervalFormat::Iso80000),
                    implode(',', $task->identifiers->all()),
                ])
            )
        );
    }

    public function test_union_merges_overlapping_tasks(): void
    {
        $set = new TaskSet($this->task('A', Interval::between(Time::at(9), Time::at(12))));

        $result = $set->union([$this->task('B', Interval::between(Time::at(11), Time::at(14)))]);

        self::assertEquals([
            '[09:00:00,11:00:00) => A',
            '[11:00:00,12:00:00) => A,B',
            '[12:00:00,14:00:00) => B',
        ], $this->format($result));
    }

    public function test_intersect_returns_only_shared_intervals(): void
    {
        $a = new TaskSet(
            $this->task('A', Interval::between(Time::at(9), Time::at(12))),
            $this->task('B', Interval::between(Time::at(13), Time::at(15)))
        );

        $b = new TaskSet(
            $this->task('A', Interval::between(Time::at(10), Time::at(14)))
        );

        $result = $a->intersect([$b]);

        self::assertEquals([
            '[10:00:00,12:00:00) => A',
            '[13:00:00,14:00:00) => B,A',
        ], $this->format($result));
    }

    public function test_interesct_returns_empty_set_if_there_is_no_intersection(): void
    {
        $a = new TaskSet(
            $this->task('A', Interval::between(Time::at(9), Time::at(12))),
            $this->task('B', Interval::between(Time::at(13), Time::at(15)))
        );

        self::assertEmpty($a->intersect([]));
    }

    public function test_gaps_detect_missing_time(): void
    {
        $set = new TaskSet(
            $this->task('A', Interval::between(Time::at(9), Time::at(11))),
            $this->task('B', Interval::between(Time::at(13), Time::at(15)))
        );

        $gaps = $set->gaps();

        self::assertEquals(['[11:00:00,13:00:00) => '], $this->format($gaps));
    }

    public function test_difference_returns_the_source_set_if_there_is_no_intersection(): void
    {
        $a = new TaskSet(
            $this->task('A', Interval::between(Time::at(9), Time::at(12))),
            $this->task('B', Interval::between(Time::at(13), Time::at(15)))
        );

        self::assertSame($a, $a->difference([]));
    }

    public function test_difference_removes_middle_overlap(): void
    {
        $a = new TaskSet(Task::for(Interval::between(Time::at(9), Time::at(12)), 'A'));
        $b = new TaskSet(Task::for(Interval::between(Time::at(10), Time::at(11)), 'B'));
        $result = $a->difference($b);

        self::assertEquals([
            '[09:00:00,10:00:00) => A',
            '[11:00:00,12:00:00) => A',
        ], $this->format($result));
    }

    public function test_difference_with_no_overlap_returns_original(): void
    {
        $a = new TaskSet(Task::for(Interval::between(Time::at(9), Time::at(12)), 'A'));
        $b = new TaskSet(Task::for(Interval::between(Time::at(13), Time::at(14)), 'B'));
        $result = $a->difference($b);

        self::assertEquals([
            '[09:00:00,12:00:00) => A',
        ], $this->format($result));
    }

    public function test_difference_full_overlap_returns_empty(): void
    {
        $a = new TaskSet(Task::for(Interval::between(Time::at(9), Time::at(12)), 'A'));
        $b = new TaskSet(Task::for(Interval::between(Time::at(8), Time::at(13)), 'B'));
        $result = $a->difference($b);

        self::assertTrue($result->isEmpty());
    }

    public function test_difference_splits_multiple_tasks(): void
    {
        $a = new TaskSet(
            Task::for(Interval::between(Time::at(9), Time::at(11)), 'A'),
            Task::for(Interval::between(Time::at(11), Time::at(13)), 'A2')
        );

        $b = new TaskSet(Task::for(Interval::between(Time::at(10), Time::at(12)), 'B'));

        $result = $a->difference($b);

        self::assertEquals([
            '[09:00:00,10:00:00) => A',
            '[12:00:00,13:00:00) => A2',
        ], $this->format($result));
    }

    public function test_difference_does_not_contain_other_tasks_attributes(): void
    {
        $a = new TaskSet(Task::for(Interval::between(Time::at(9), Time::at(12)), 'A'));
        $b = new TaskSet(Task::for(Interval::between(Time::at(10), Time::at(11)), 'B'));
        $result = $a->difference($b);

        foreach ($result as $task) {
            self::assertFalse($task->identifiers->has('B'));
        }
    }

    public function test_difference_edge_touching_boundaries(): void
    {
        $a = new TaskSet(Task::for(Interval::between(Time::at(9), Time::at(12)), 'A'));
        $b = new TaskSet(Task::for(Interval::between(Time::at(12), Time::at(13)), 'B'));

        $result = $a->difference($b);

        self::assertEquals([
            '[09:00:00,12:00:00) => A',
        ], $this->format($result));
    }

    public function test_difference_circular_intervals(): void
    {
        $a = new TaskSet(Task::for(Interval::between(Time::at(22), Time::at(2)), 'A'));
        $b = new TaskSet(Task::for(Interval::between(Time::at(23), Time::at(1)), 'B'));

        $result = $a->difference($b);

        self::assertNotEmpty($result);
    }

    public function test_active_at_returns_only_matching_tasks(): void
    {
        $set = new TaskSet(
            Task::for(Interval::between(Time::at(9), Time::at(12)), 'morning'),
            Task::for(Interval::between(Time::at(13), Time::at(15)), 'afternoon'),
            Task::for(Interval::between(Time::at(18), Time::at(20)), 'evening'),
        );

        $result = $set->includes(Time::at(13, 30));

        self::assertCount(1, $result);
        self::assertInstanceOf(Task::class, $result->first());
        self::assertEquals(['afternoon'], $result->first()->identifiers->all());
    }

    public function test_active_at_returns_multiple_overlapping_tasks(): void
    {
        $set = new TaskSet(
            Task::for(Interval::between(Time::at(9), Time::at(14)), 'A'),
            Task::for(Interval::between(Time::at(12), Time::at(16)), 'B'),
            Task::for(Interval::between(Time::at(10), Time::at(11)), 'C'),
        );

        $result = $set->includes(Time::at(12, 30));

        self::assertCount(2, $result);
        self::assertEquals(['A'], $result->get(0)->identifiers->all());
        self::assertInstanceOf(Task::class, $result->last());
        self::assertEquals(['B'], $result->last()->identifiers->all());
    }

    public function test_active_at_respects_interval_boundaries(): void
    {
        $set = new TaskSet(Task::for(Interval::between(Time::at(10), Time::at(12)), 'A'));

        self::assertCount(1, $set->includes(Time::at(10)));
        self::assertCount(0, $set->includes(Time::at(12)));
    }

    public function test_active_at_returns_empty_when_no_match(): void
    {
        $set = new TaskSet(Task::for(Interval::between(Time::at(9), Time::at(10)), 'A'));

        $result = $set->includes(Time::at(12));

        self::assertTrue($result->isEmpty());
    }

    private function basicTaskSet(): TaskSet
    {
        return new TaskSet(
            Task::for(Interval::between(Time::at(9), Time::at(10)), 'A'),
            Task::for(Interval::between(Time::at(12), Time::at(13)), 'B'),
            Task::for(Interval::between(Time::at(15), Time::at(16)), 'C'),
        );
    }

    public function test_next_finds_next_task_after_time(): void
    {
        $firstTask = $this->basicTaskSet()
            ->next(Time::at(11), SearchMode::Linear)
            ->first();

        self::assertInstanceOf(Task::class, $firstTask);
        self::assertSame('B', $firstTask->identifiers->first());
    }

    public function test_next_includes_start_boundary(): void
    {
        $firstTask = $this->basicTaskSet()
            ->next(Time::at(12), SearchMode::Linear)
            ->first();

        self::assertInstanceOf(Task::class, $firstTask);
        self::assertSame('B', $firstTask->identifiers->first());
    }

    public function test_next_linear_returns_null_if_none(): void
    {
        self::assertTrue(
            $this
                ->basicTaskSet()
                ->next(Time::at(17), SearchMode::Linear)
                ->isEmpty()
        );
    }

    public function test_set_duration(): void
    {
        self::assertTrue(
            $this->basicTaskSet()
                ->duration()
                ->equals(
                    $this->basicTaskSet()
                        ->toIntervalSet()
                        ->duration()
                )
        );
    }

    public function test_next_circular_wraps_to_first(): void
    {
        $firstTask = $this->basicTaskSet()
            ->next(Time::at(17), SearchMode::Circular)
            ->first();

        self::assertInstanceOf(Task::class, $firstTask);
        self::assertSame('A', $firstTask->identifiers->first());
    }

    public function test_previous_finds_previous_task(): void
    {
        $firstTask = $this->basicTaskSet()
            ->previous(Time::at(11), SearchMode::Linear)
            ->first();

        self::assertInstanceOf(Task::class, $firstTask);
        self::assertSame('A', $firstTask->identifiers->first());
    }

    public function test_previous_does_not_include_current_boundary(): void
    {
        $firstTask = $this->basicTaskSet()
            ->previous(Time::at(12), SearchMode::Linear)
            ->first();

        self::assertInstanceOf(Task::class, $firstTask);
        self::assertSame('A', $firstTask->identifiers->first());
    }

    public function test_previous_circular_wraps_to_last(): void
    {
        $firstTask = $this->basicTaskSet()
            ->previous(Time::at(8), SearchMode::Circular)
            ->first();

        self::assertInstanceOf(Task::class, $firstTask);
        self::assertSame('C', $firstTask->identifiers->first());
    }

    public function test_around_finds_closest_task(): void
    {
        $firstTask = $this->basicTaskSet()
            ->nearest(Time::at(11))
            ->first();

        self::assertInstanceOf(Task::class, $firstTask);
        self::assertSame('B', $firstTask->identifiers->first());
    }

    public function test_around_prefers_forward_when_tie(): void
    {
        $firstTask = $this->basicTaskSet()
            ->nearest(Time::at(11, 30))
            ->first();

        self::assertInstanceOf(Task::class, $firstTask);
        self::assertSame('B', $firstTask->identifiers->first());
    }

    public function test_around_at_start_boundary(): void
    {
        $firstTask = $this->basicTaskSet()
            ->nearest(Time::at(9))
            ->first();

        self::assertInstanceOf(Task::class, $firstTask);
        self::assertSame('A', $firstTask->identifiers->first());
    }

    public function test_previous_and_next_are_disjoint(): void
    {
        $t = Time::at(12);
        $tasks = $this->basicTaskSet();

        self::assertNotSame(
            $tasks->previous($t, SearchMode::Linear)->first()?->identifiers->first(),
            $tasks->next($t, SearchMode::Linear)->first()?->identifiers->first()
        );
    }

    public function test_nearest_prefers_forward_when_equidistant(): void
    {
        $tasks = new TaskSet(
            Task::for(Interval::between(Time::at(10), Time::at(11)), 'A'),
            Task::for(Interval::between(Time::at(12), Time::at(13)), 'B'),
        );

        // midpoint between both
        $result = $tasks->nearest(Time::at(11, 30))->first();

        self::assertInstanceOf(Task::class, $result);
        self::assertSame('B', $result->identifiers->first());
    }

    public function test_nearest_is_consistent_with_direction_bias(): void
    {
        $tasks = new TaskSet(
            Task::for(Interval::between(Time::at(10), Time::at(11)), 'A'),
            Task::for(Interval::between(Time::at(12), Time::at(13)), 'B'),
        );

        $t = Time::at(11, 30);

        $forward = $tasks->next($t, SearchMode::Circular)->first();
        $nearest = $tasks->nearest($t)->first();

        self::assertInstanceOf(Task::class, $forward);
        self::assertInstanceOf(Task::class, $nearest);
        self::assertSame($forward->identifiers->first(), $nearest->identifiers->first());
    }

    public function test_duration_can_be_serialized_and_unserialized(): void
    {
        $tasks = new TaskSet(
            Task::for(Interval::between(Time::at(10), Time::at(11)), 'A'),
            Task::for(Interval::between(Time::at(12), Time::at(13)), 'B'),
        );
        $restored = unserialize(serialize($tasks));

        self::assertInstanceOf(TaskSet::class, $restored);
        self::assertEquals($tasks, $restored);
    }
}
