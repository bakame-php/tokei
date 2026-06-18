---
layout: default
title: Event
---

# Event

`Bakame\Tokei\Event` extends `Bakame\Tokei\Time` semantics by associating one or more identifiers.

## Relationship with Time

`Event` preserves all temporal behavior from `Time` while adding identification metadata.

| Time               | Event                                  |
|--------------------|----------------------------------------|
| Time instant       | Time instant + identifiers             |
| Temporal methods   | available through `Event::at`          |
| Identifier methods | available through `Event::identifiers` |
| Formatting         | same formatting rules as `Time`        |


## Instantiation

```php
Event::at(Time $at, Identifiers|string $identifier = new Identifiers()): self
Event::fromFormat(string $value, TimeFormat $format = TimeFormat::Iso8601): self
```

## Accessors

An `Event` exposes two readonly public properties:

- `Event::at`: the underlying `Time`
- `Event::identifiers`: the associated `Identifiers`

All temporal operations available on `Time` and all identifier operations available on `Identifiers` can be accessed through these properties.

```php
$event = Event::at(Time::noon(), 'lunch');
$event->at;           // returns Time instance
$event->identifiers;  // returns Identifiers instance
```

## Formatting

`Event` uses the same formatting rules as `Time` but extends the generated representation
by appending an identifier component separated by a semicolon (`;`). Multiple identifiers
are represented as comma-separated values.

```php
$event = Event::at(Time::noon(), ['lunch', 'break']);
$event->format(TimeFormat::Compact);   // returns 12h00m00s;lunch,break
```

#### Updating time and identifiers

```php
Event::occursOn(Time $at): self
Event::named(Identifiers $identifier): self
```

## Strict Comparison

The method compares the instance time as well as its identifiers.

```php
Event::equals(Event $other): bool
```

## Interacting with PHP's native Date API

```php
Event::toNative(DateTimeInterface $reference): NativeEvent
```

The reference `DateTimeInterface` object is used to compute the starting date
using `Time::applyTo` method.

`NativeEvent` is an immutable DTO exposing two public readonly properties:

- `at` (`DateTimeImmutable`)
- `identifiers` (`Identifiers`)

```php
$event = Event::at(Time::noon(), 'lunch');
$native = $event->toNative(new DateTime('2026-12-03 13:03:57'));
$native->at::class; 
// 'DateTimeImmutable'

$native->identifiers::class;
// 'Bakame\Tokei\Identifiers'
```

> [!NOTE]
> The supplied `DateTimeInterface` object is used as the date component when
> converting the `Time` instance into a native PHP date object 
> through `Time::applyTo`.

It is possible to create an `Event` instance from a `NativeEvent`.

```php
$nativeEvent = new NativeEvent(
    new DateTimeImmutable('2026-12-03 13:03:57'),
    new Identifiers('lunch-break-end')
);

$event = Event::fromNative($nativeEvent);
$event->at->format();
// returns '13:03:57'

$event->identifiers->primary();
// returns 'lunch-break-end'
```