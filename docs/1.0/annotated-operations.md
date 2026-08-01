---
layout: default
title: Identified Operations
---

# Introduction

Support for identified Temporal Value Objects means that temporal objects can now carry identifiers
and preserve that information through temporal operations.

The following example demonstrates the difference between operating on identified temporal values
and operating only on their underlying time ranges.

## Identified Temporal Value Objects

```php
<?php

use Bakame\Tokei\Duration;
use Bakame\Tokei\Interval;
use Bakame\Tokei\IntervalFormat;
use Bakame\Tokei\IntervalSet;
use Bakame\Tokei\Task;
use Bakame\Tokei\TaskSet;
use Bakame\Tokei\Time;

$taskSet = new TaskSet(
    Task::for(Interval::since(Time::at(hour: 1), Duration::of(hours: 2)), 'early-morning'),
    Task::for(Interval::between(Time::at(hour: 9), Time::at(hour:12, minute: 30)), 'morning'),
    Task::for(Interval::between(Time::at(hour:19), Time::at(hour:23, minute: 30)), 'evening'),
);
$task = Task::for(Interval::between(Time::at(22, 30), Time::at(2)), 'late-evening');
```

Now let's execute a union with the `$task` object.

```php
$taskSet->union($task)->formatAll(IntervalFormat::Iso80000);
// returns
// [
//    "[01:00:00,02:00:00);early-morning,late-evening",
//    "[02:00:00,03:00:00);early-morning",
//    "[09:00:00,12:30:00);morning",
//    "[19:00:00,22:30:00);evening",
//    "[22:30:00,23:30:00);evening,late-evening",
//    "[23:30:00,01:00:00);late-evening",
// ]
```

The initial task sets contains:

- `early-morning`: `[01:00,03:00)`
- `morning`: `[09:00,12:30)`
- `evening`: `[19:00,23:30)`
-  `late-evening`: `[22:30,02:00)` (a range wrapping around midnight)

Because identifiers are preserved, the union operation can keep track of which tasks are active
for each resulting interval:

- `[22:30,23:30)` → both `evening` and `late-evening`
- `[23:30,02:00)` → only `late-evening`
- `[01:00,02:00)` → both `early-morning` and `late-evening`
- `[02:00,03:00)` → only `early-morning`

## Unidentified Temporal Value Objects

We can create an unidentified version of the task set by converting it to an IntervalSet:

```php
$intervalSet = new IntervalSet($taskSet);
// the IntervalSet strips all identifiers
// from the tasks.
```

and perform the same union operation:

```php
$intervalSet->union($task)->formatAll(IntervalFormat::Iso80000);
// returns
// [
//    "[09:00:00,12:30:00)"
//    "[19:00:00,03:00:00)"
// ]
```

Since no identifiers are present anymore, the operation only considers the temporal coverage.
The overlapping ranges are therefore collapsed into the smallest possible set of intervals.

This illustrates the difference between `TaskSet` and `IntervalSet`: a `TaskSet` carries semantic
information in addition to time boundaries, while an `IntervalSet` only represents temporal coverage.