---
layout: default
title: Task
---

# Task

`Bakame\Tokei\Task` extends `Bakame\Tokei\Interval` by associating one or more identifiers while preserving all temporal behavior.

## Relationship with Interval

`Task` preserves all temporal behavior from `Interval` while adding identification metadata.

| Interval           | Task                                  |
|--------------------|---------------------------------------|
| Time span          | Time span + identifiers               |
| Temporal methods   | available through `Task::interval`    |
| Identifier methods | available through `Task::identifiers` |
| Formatting         | same formatting rules as `Interval`   |


## Instantiation

```php
Task::for(Interval $interval, Identifiers|string $identifier = new Identifiers()): self
Task::fromEvent(Event $event, Duration $duration, Bound $from): self
Task::fromFormat(string $value, IntervalFormat $format, ?Unit $unit = null): self
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