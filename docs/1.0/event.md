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
Event::fromFormat(string $value, TimeFormat $format): self
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
$event->format(TimeFormat::Compact);   // returns 12h0m0s;lunch,break
```

## Modifying events

```php
Event::occursOn(Time $at): self
Event::named(Identifiers $identifier): self
```

## Strict Comparison

The method compares the instance time as well as its identifiers.

```php
Event::equals(Event $other): bool
```