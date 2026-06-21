<?php

declare(strict_types=1);

namespace Bakame\Tokei;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function serialize;
use function unserialize;

#[CoversClass(Task::class)]
#[CoversClass(Identifiers::class)]
final class TaskTest extends TestCase
{
    public function testConstructsTask(): void
    {
        $interval = Interval::between(Time::at(10), Time::at(12));
        $task = Task::for($interval);

        self::assertSame($interval, $task->interval);
        self::assertTrue($task->identifiers->isEmpty());
    }

    public function testConstructsTaskWithAttributes(): void
    {
        $task = Task::for(
            Interval::between(Time::at(10), Time::at(12)),
            $attributes = new Identifiers(['John'])
        );

        self::assertSame($attributes, $task->identifiers);
    }

    public function testShiftReturnsSameInstanceWhenIntervalIsEqual(): void
    {
        $task = Task::for($interval = Interval::between(Time::at(10), Time::at(12)));

        self::assertSame($task, $task->during($interval));
    }

    public function testShiftReturnsNewTask(): void
    {
        $task = Task::for(Interval::between(Time::at(10), Time::at(12)));
        $other = Interval::between(Time::at(13), Time::at(15));
        $shifted = $task->during($other);

        self::assertNotSame($task, $shifted);
        self::assertEquals($other, $shifted->interval);
        self::assertSame($task->identifiers, $shifted->identifiers);
    }

    public function testWithAttributesReturnsSameInstanceWhenEqual(): void
    {
        $task = Task::for(
            Interval::between(Time::at(10), Time::at(12)),
            $attributes = new Identifiers(['John'])
        );

        self::assertSame($task, $task->named($attributes));
    }

    public function testWithAttributesReturnsNewTask(): void
    {
        $task = Task::for(Interval::between(Time::at(10), Time::at(12)));
        $updated = $task->named('John');

        self::assertNotSame($task, $updated);
        self::assertSame(['John'], $updated->identifiers->all());
        self::assertEquals($task->interval, $updated->interval);
    }

    public function test_duration_can_be_serialized_and_unserialized(): void
    {
        $task = Task::for(Interval::between(Time::at(10), Time::at(12)), 'julio');
        $restored = unserialize(serialize($task));

        self::assertInstanceOf(Task::class, $restored);
        self::assertTrue($task->equals($restored));
    }
}
