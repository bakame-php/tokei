---
layout: default
title: Principes
---

# Principles

## Core Purpose

`Tokei` is a framework-agnostic package that provides a consistent and expressive
way to work with temporal values in a safe and predictable manner. Its design
focuses on preserving temporal semantics while offering a coherent API for
representing and manipulating time-related data.

## Temporal Building Blocks

The package provides the following core temporal classes under the `Bakame\Tokei` namespace.
These primitives contain the temporal logic used throughout the package and should
generally be learned first.

**Temporal Values**

- [Duration](duration.md)
- [Time](time.md)
- [Interval](interval.md)
- [IntervalSet](intervalset.md)

## Annotated Temporal Values

Annotated temporal values extend the core temporal primitives by associating identifiers
with them while preserving the same temporal semantics. They enrich existing temporal
models with identification and context without introducing separate temporal behavior.

**Annotation Values**

- [Identifiers](identifiers.md)

**Annotated Temporal Values**

- [Event](event.md)
- [Task](task.md)
- [EventSet](eventset.md)
- [TaskSet](taskset.md)

| Primitives  | Annotated Values |
|-------------|------------------|
| Time        | Event            |
| Interval    | Task             |
| IntervalSet | TaskSet          |

## TL;DR

Keep the following in mind:

- Learn `Time`, `Duration`, `Interval`, `IntervalSet` first.
- `Event`, `Task` and `TaskSet` are enrichments rather than separate models.
- Temporal logic lives in the primitives.
- Annotated values primarily add identification and contextual information through `Identifiers`.
- Check the [Accepted Input Types](accepted-input-types.md) documentation to understand how values are converted between temporal representations for easier day-to-day usage.