---
layout: default
title: Identifiers
---

# Identifiers

## Instantiation

```php
Identifiers::__construct(string ...$items);
```

## Formatting

```php
Identifiers::fromCommaSeparated(string $value): self
Identifiers::toCommaSeparated(): string
```

## Accessors

```php
Identifiers::count(): int
Identifiers::getIterator(): Iterator
Identifiers::jsonSerialize(): array
Identifiers::all(): array
Identifiers::isEmpty(): bool
Identifiers::has(string ...$values): bool
Identifiers::get(int $offset): string
Identifiers::nth(int $offset): ?string
```

## Equality

```php
Identifiers::equals(HasIdentifiers|Identifiers $other): bool
```

## Modifiers

```php
Identifiers::only(string ...$labels): self
Identifiers::except(string ...$labels): self
Identifiers::sorted(Direction $direction = Direction::Ascending): self
Identifiers::sortedUsing(callable $callback): self
Identifiers::merge(Identifiers ...$others): self
```