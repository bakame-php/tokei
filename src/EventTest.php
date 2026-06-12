<?php

declare(strict_types=1);

namespace Bakame\Tokei;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Event::class)]
#[CoversClass(Identifiers::class)]
final class EventTest extends TestCase
{
    public function testConstructsTask(): void
    {
        $at = Time::at(10);
        $event = Event::at($at);

        self::assertSame($at, $event->at);
        self::assertTrue($event->identifiers->isEmpty());
    }

    public function testConstructsTaskWithAttributes(): void
    {
        $task = Event::at(
            Time::at(10),
            $identifiers = new Identifiers(['John'])
        );

        self::assertSame($identifiers, $task->identifiers);
    }

    public function testShiftReturnsSameInstanceWhenIntervalIsEqual(): void
    {
        $event = Event::at($at = Time::at(10));

        self::assertSame($event, $event->occursOn($at));
    }

    public function testShiftReturnsNewTask(): void
    {
        $event = Event::at(Time::at(10));
        $other = Time::at(13);
        $shifted = $event->occursOn($other);

        self::assertNotSame($event, $shifted);
        self::assertEquals($other, $shifted->at);
        self::assertSame($event->identifiers, $shifted->identifiers);
    }

    public function testWithAttributesReturnsSameInstanceWhenEqual(): void
    {
        $event = Event::at(Time::at(10), $attributes = new Identifiers(['John']));

        self::assertSame($event, $event->named($attributes));
    }

    public function testWithAttributesReturnsNewTask(): void
    {
        $event = Event::at(Time::at(10));
        $updated = $event->named('John');

        self::assertNotSame($event, $updated);
        self::assertSame(['John'], $updated->identifiers->all());
        self::assertEquals($event->at, $updated->at);
    }
}
