---
layout: default
title: Tokei
---

# Tokei

[![Author](http://img.shields.io/badge/author-@nyamsprod-blue.svg?style=flat-square)](https://phpc.social/@nyamsprodd)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE)
[![Build](https://github.com/bakame-php/tokei/workflows/build/badge.svg)](https://github.com/bakame-php/tokei/actions?query=workflow%3A%22build%22)
[![Latest Version](https://img.shields.io/github/release/bakame-php/tokei.svg?style=flat-square)](https://github.com/bakame-php/tokei/releases)
[![Total Downloads](https://img.shields.io/packagist/dt/bakame/tokei.svg?style=flat-square)](https://packagist.org/packages/bakame/tokei)
[![Sponsor development of this project](https://img.shields.io/badge/sponsor%20this%20package-%E2%9D%A4-ff69b4.svg?style=flat-square)](https://github.com/sponsors/nyamsprod)

**Tokei** (pronounced: [to̞ke̞ː] or [tokeː]) is a lightweight domain-focused set of immutable value objects for representing
and operating on time, durations, including, circular 24-hour intervals, and interval sets, offering
expressive temporal modeling without timezone handling.

Once installed you will be able to do the following:

```php

use Bakame\Tokei\Duration;
use Bakame\Tokei\DurationFormat;
use Bakame\Tokei\Time;

$target = Duration::of(hours: 7, minutes: 33);
$alreadyDone = Duration::of(hours: 5, minutes: 17);
$remaining = $target->sum($alreadyDone->negated());
$startedNewShiftAt = Time::at(hour: 21, minute: 31);
$shouldStopAt = $startedNewShiftAt->shift($remaining);

echo "I have to work ", $target->format(DurationFormat::Compact), " today", PHP_EOL;
echo "I have already worked for ", $alreadyDone->format(DurationFormat::Compact), PHP_EOL;
echo "I still have to work ", $remaining->format(DurationFormat::Compact), PHP_EOL;
echo "If I start working again at ", $startedNewShiftAt->format(), PHP_EOL;
echo "I will end today's shit at ", $shouldStopAt->format(), PHP_EOL;
```
    
will output the following:

```bash
I have to work 7h33m today
I have already worked for 5h17m
I still have to work 2h16m
If I start working again at 21:31:00
I will end today's shit at 23:47:00
```

The framework-agnostic package offers a consistent and expressive way to work with temporal values in a safe
and predictable manner.

The package comes with the following Temporal classes under the `Bakame\Tokei` namespace:

**Temporal Values**

- [Time](1.0/time.md)
- [Duration](1.0/duration.md)
- [Interval](1.0/interval.md)
- [IntervalSet](1.0/intervalset.md)

**Annotation Values**

- [Identifiers](identifiers.md)

**Annotated Temporal Values**

- [Event](1.0/event.md)
- [Task](1.0/task.md)
- [EventSet](eventset.md)
- [TaskSet](1.0/taskset.md)

See also: [Accepted Input Types](1.0/accepted-input-types.md) for how values are converted
between temporal representations.

Annotated temporal values extend the core temporal primitives by associating identifiers with them
while preserving the same temporal semantics.

| Primitives  | Annotated Values |
|-------------|------------------|
| Time        | Event            |
| Interval    | Task             |
| IntervalSet | TaskSet          |

- Learn `Time`, `Duration`, `Interval`, `IntervalSet` first. 
- `Event`, `Task` and `TaskSet` are enrichments, not separate models. 
- Temporal logic lives in the primitives. 
- Annotated values mostly add identification and context.

