---
layout: default
title: Identifiers
---

# Identifiers

`Bakame\Tokei\Identifiers` is a collection of non-empty string values used to tag and track the origin of
data throughout processing. The class implements PHP's `Countable` and `IteratorAggregate` interfaces to ease
counting and iterating over its content.

The main purpose of this structure is to preserve data provenance: whenever values are transformed or combined,
their original identifiers can be carried along and inspected later.

## Identifiers Rules

Each identifier must follow these rules:

- Must be a non-empty string
- Must contain only lowercase letters (`a–z`), digits (`0–9`), dots (`.`), underscores (`_`), or hyphens (`-`)
- Must start with a letter or digit
- Must end with a letter or digit
- Must not start or end with `.`,`_`, or `-`
- Must not contain consecutive occurrences of `.`, `_`, or ``- (e.g. `..`, `__`, `--`, or mixed sequences like `._`, `_-`, etc.)

These constraints ensure identifiers remain safe, stable, and predictable across serialization, merging, and comparison operations.

## Instantiation

```php
Identifiers::__construct(string ...$items);
```

All values passed to the constructor must satisfy the identifier rules above.

## Formatting

Identifiers can be serialized into and reconstructed from a comma-separated string.

When converted to string, identifiers are joined using a comma (`,`).
If the collection is empty, the string representation is an empty string.


```php
Identifiers::fromCommaSeparated(string $value): self
Identifiers::toCommaSeparated(): string
```

<p class="message-notice">Whitespace handling is strict; input values should already be normalized and valid identifiers.</p>

## Accessors

```php
Identifiers::count(): int
Identifiers::getIterator(): Iterator
Identifiers::jsonSerialize(): array
Identifiers::all(): array
Identifiers::isEmpty(): bool
Identifiers::has(string ...$values): bool
Identifiers::primary(): ?string
Identifiers::get(int $offset): string
Identifiers::nth(int $offset): ?string
```

- `primary()` returns the first identifier in the collection, or `null` if empty.
- Order is preserved internally but is not used for equality comparison (see below).
- the JSON representation of the instance uses the result of the `toCommaSeparated()`method.

## Equality

```php
Identifiers::equals(Identifiers $other): bool
```

<p class="message-notice">Two instances are considered equal if they contain the <strong>same set of values</strong>, regardless of order.</p>
<p class="message-info">Order is intentionally ignored to reflect the semantic meaning of identifiers as a set rather than a sequence.</p>

## Modifiers

```php
Identifiers::only(string ...$labels): self
Identifiers::except(string ...$labels): self
Identifiers::sorted(SortDirection $direction = SortDirection::Ascending): self
Identifiers::sortedUsing(callable $callback): self
Identifiers::merge(Identifiers ...$others): self
```

All modifier methods are immutable: they return a new instance unless no change is required.

- `only()` keeps only the specified identifiers.
- `except()` removes the specified identifiers.
- `sorted()` returns a sorted copy using a predefined direction.
- `sortedUsing()` allows custom ordering logic.
- `merge()` combines multiple collections while preserving provenance.

<p class="message-info"><code>merge()</code> is especially useful in algebraic or transformation operations where tracking the origin of combined values is required.</p>