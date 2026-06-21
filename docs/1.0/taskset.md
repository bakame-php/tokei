---
layout: default
title: Task
---

# TaskSet

`Bakame\Tokei\TaskSet` extends `Bakame\Tokei\IntervalSet` by associating one or more identifiers while preserving all temporal behavior.

## Relationship with Interval

`TaskSet` preserves all temporal behavior from `IntervalSet` while adding identification metadata.

| IntervalSet                     | TaskSet                                    |
|---------------------------------|--------------------------------------------|
| Collection of Interval | Collection of Tasks                        |
| Temporal methods                | available through `IntervalSet::fromTasks` |
| Formatting                      | same formatting rules as `IntervalSet`     |


## Instantiation

```php
TaskSet::for(Interval $interval, Identifiers|string $identifier = new Identifiers()): self
Task::fromEvent(Event $event, Duration $duration, Bound $from): self
Task::fromFormat(string $value, IntervalFormat $format = IntervalFormat::Iso8601StartDuration, ?Unit $unit = null): self
```

## Accessors

A `Task` exposes two readonly public properties:

- `Task::interval`: the underlying `Interval`
- `Task::identifiers`: the associated `Identifiers`

All temporal operations available on `Interval` and all identifier operations available on `Identifiers` can be accessed through these properties.

```php
$task = Task::for(
    Interval::since(
        Time::noon(),
        Duration::of(hours: 2, minutes: 30)
    ),
    'after-lunch-talks'
);
$task->interval;     // returns Interval instance
$task->identifiers;  // returns Identifiers instance
```

## Formatting

`Task` uses the same formatting rules as `Interval` but extends the generated representation
by appending an identifier component separated by a semicolon (`;`). Multiple identifiers
are represented as comma-separated values.

```php
$task = Task::for(
    Interval::between(Time::noon(), Time::at(hour: 14, minute: 30)),
    new Idendifiers('after-lunch-talks', 'main-talk')
);
$task->format(IntervalFormat::Iso8601StartEnd);
// returns 12:00:00/14:30:00;after-lunch-talks,main-talk
```

## Updating interval and identifiers

```php
Task::during(Interval $interval): self
Task::named(Identifiers $identifier): self
```

## Strict Comparison

The method compares the instance interval as well as its identifiers.

```php
Task::equals(Task $other): bool
```

## Interacting with PHP's native Date API

```php
Task::toNative(DateTimeInterface $reference): NativeTask
```

The reference `DateTimeInterface` object is used to compute the starting date
using `Time::applyTo` method.

`NativeTask` is an immutable DTO exposing two public readonly properties:

- `interval` (`NativeInterval`)
- `identifiers` (`Identifiers`)

```php
$task = Task::for(
    Interval::between(
        Time::noon(),
        Time::at(hour: 14, minute: 30)
    ), 
    'after-lunch-talks'
);
$native = $task->toNative(new DateTime('2026-12-03 13:03:57'));
$native->interval::class; 
// 'Bakame\Tokei\NativeInterval'

$native->identifiers::class;
// 'Bakame\Tokei\Identifiers'
```

<p class="message-info">The supplied <code>DateTimeInterface</code> object provides the date
component used when converting the task interval into native PHP date objects.</p>

A `Task` instance can also be created from a `NativeTask`.

```php
$native = new NativeInterval(
    new DateTimeImmutable('2026-12-03 13:03:57'),
    new DateTimeImmutable('2026-12-05 11:19:57'),
);

$nativeTask = new NativeTask($native, new Identifiers('after-lunch-talks'));

$task = Task::fromNative($nativeTask);
$task->interval::class; 
// 'Bakame\Tokei\Interval'

$task->identifiers::class;
// 'Bakame\Tokei\Identifiers'
```