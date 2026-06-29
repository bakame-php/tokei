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

- [Duration](duration.md)
- [Time](time.md)
- [Interval](interval.md)
- [IntervalSet](intervalset.md)

**Annotation Values**

- [Identifiers](identifiers.md)

**Annotated Temporal Values**

- [Event](event.md)
- [Task](task.md)
- [EventSet](eventset.md)
- [TaskSet](taskset.md)

See also: [Accepted Input Types](accepted-input-types.md) for how values are converted
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