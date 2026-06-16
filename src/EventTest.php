<?php

declare(strict_types=1);

namespace Bakame\Tokei;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
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

    #[DataProvider('validFormats')]
    public function testCanCreateEventFromFormattedString(
        string $input,
        string $expectedTime,
        string $expectedIdentifier,
    ): void {
        $event = Event::fromFormat($input);

        self::assertSame($expectedTime, $event->at->format());
        self::assertSame($expectedIdentifier, $event->identifiers->asCommaSeparated());
    }

    /**
     * @return iterable<non-empty-string, array{0: string, 1: non-empty-string, 2: string}>
     */
    public static function validFormats(): iterable
    {
        yield 'no spaces' => [
            '12:00:23;larry-king',
            '12:00:23',
            'larry-king',
        ];

        yield 'space after separator' => [
            '12:00:23; larry-king',
            '12:00:23',
            'larry-king',
        ];

        yield 'space before separator' => [
            '12:00:23 ;larry-king',
            '12:00:23',
            'larry-king',
        ];

        yield 'spaces around separator' => [
            '12:00:23 ; larry-king',
            '12:00:23',
            'larry-king',
        ];

        yield 'multiple spaces around separator' => [
            '12:00:23     ;     larry-king',
            '12:00:23',
            'larry-king',
        ];

        yield 'missing identifier' => [
            '12:00:23;',
            '12:00:23',
            '',
        ];

        yield 'multiple spaces between identifier separator' => [
            '12:00:23     ;     larry-king , junior',
            '12:00:23',
            'larry-king,junior',
        ];
    }

    #[DataProvider('invalidFormats')]
    public function testRejectsInvalidFormats(string $input): void
    {
        $this->expectException(TokeiException::class);

        Event::fromFormat($input);
    }

    /**
     * @return iterable<non-empty-string, array{0: string}>
     */
    public static function invalidFormats(): iterable
    {
        yield 'empty string' => [''];

        yield 'missing separator' => [
            '12:00:23 larry-king',
        ];

        yield 'missing time' => [
            ';larry-king',
        ];

        yield 'invalid time' => [
            '25:00:00;larry-king',
        ];

        yield 'extra separator' => [
            '12:00:23;larry;king',
        ];

        yield 'empty identifier' => [
            '12:00:23;larry,,king',
        ];
    }
}
