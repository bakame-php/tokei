---
layout: homepage
title: Tokei
---

# Tokei

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