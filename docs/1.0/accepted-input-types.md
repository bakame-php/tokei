---
layout: default
title: Duration
---

# Accepted Input Types

`Tokei` methods accept multiple representations of the same temporal concept. 
Values are automatically converted when possible.

## Time values

A time can be expressed using any of the following types:

- `Time`
- `Event`
- `DateTimeInterface`
- `NativeEvent`

Example:

```php
use Bakame\Tokei\Interval;

Interval::between(Time::noon(), Time::endOfDay());
Interval::between(new DateTime('2026-05-23 15:23:16'), Time::endOfDay());
Interval::between(Event::at(Time::noon(), 'lunch'), new DateTime('2026-05-23 15:23:16'));
```

<p class="message-notice">The date and timezone components of the <code>DateTimeInterface</code> object are 
ignored when used as a <code>Time</code> instance parameter.</p>

```php
$interval = Interval::between(
    Event::at(Time::noon(), 'lunch'),
    new DateTime('2026-05-23 15:23:16', new DateTimeZone('Europe/Brussels'))
);
$interval->end->format();
// returns '15:23:16'
```

## Duration values

A duration can be expressed using any of the following types:

- `Duration`
- `DateInterval`
- `Interval`
- `Task`
- `NativeInterval`
- `NativeTask`

Example:

```php
Interval::since(
    new DateTime('2026-05-23 15:23:16'), 
    new DateInterval('PT3H')
);

Interval::since(
    new DateTime('2026-05-23 15:23:16'), 
    Duration::of(hours: 3),
);
```

For Interval types, the interval `duration` property will be used.

<p class="message-warning"><code>DateInterval</code> instances which do not 
use deterministic component will  be rejected and throw an <code>InvalidDuration</code>
exception if provided in place of a <code>Duration</code> instance.</p>

```php
Interval::since(
    new DateTime('2026-05-23 15:23:16'), 
    new DateInterval('P1MT3H')
);
//will throw because the month component is used
```
<p class="message-info">When a <code>DateInterval</code> instance is generated through <code>DateTimeInterface::diff</code>, 
intervals containing months or years are still accepted because the <code>DateInterval::days</code> property contains
the resolved duration in days.</p>

## Interval values

An interval can be expressed using any of the following types:

- `Interval`
- `Task`
- `NativeInterval`
- `NativeTask`

All remarks related to `DateTimeInterface` and `DateInterval` usages are
applicable for interval types.

## Identifier values

Identifiers can be expressed using:

- `Identifiers`
- classes implementing the  `HasIdentifiers` interface
- `string`
- `iterable<Identifiers|HasIdentifiers|string>`

## Argument rules

| Concept     | 	Accepted representations                                                       |
|-------------|---------------------------------------------------------------------------------|
| Time	       | `Time`, `Event`, `DateTimeInterface`, `NativeEvent`                             | 
| Duration    | 	`Duration`, `DateInterval`, `Interval`, `Task`, `NativeInterval`, `NativeTask` |       
| Interval    | 	`Interval`, `Task`, `NativeInterval`, `NativeTask`                             |   
| Identifiers | `Identifiers`, `HasIdentifiers`, `string`, `iterable`                           |

Unless stated otherwise, any method accepting a temporal
primitive also accepts any compatible representation of that primitive.

## Time vs Native representations

`Tokei` distinguishes between two temporal domains:

### Time-based types (no date/timezone)

- `Time`
- `Event`
- `Interval`
- `Task`

These types represent time-of-day semantics only.  
Date and timezone information are not part of their model.

### Native types (absolute datetime)

- `NativeEvent`
- `NativeInterval`
- `NativeTask`
- `DateTimeInterface`

These types represent real-world instants and preserve date and timezone information.

### Conversion rule

When a `DateTimeInterface` is used in a Time-based context, only the time-of-day is used.
When used in a Native context, the full datetime (date + timezone) is preserved.