---
layout: default
title: Installation
---

# Installation

## System Requirements

**PHP >= 8.4** is required but the latest stable version of PHP is recommended.

## Installation

**Tokei** is available on [Packagist](https://packagist.org/packages/bakame/tokei) and can be installed using [Composer](https://getcomposer.org/):

~~~
composer require bakame/tokei
~~~

You **MAY** need:

- The `ext-intl` extension or use a polyfill for `IntlDateFormatter` to be able to get the locale string version of the time.


## Principles

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