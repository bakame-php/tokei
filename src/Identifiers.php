<?php

declare(strict_types=1);

namespace Bakame\Tokei;

use Countable;
use Iterator;
use IteratorAggregate;
use JsonSerializable;

use function array_diff;
use function array_fill_keys;
use function array_filter;
use function array_keys;
use function array_values;
use function count;
use function explode;
use function implode;
use function in_array;
use function is_string;
use function preg_match;
use function rsort;
use function sort;
use function trim;

use const SORT_STRING;

/**
 * @phpstan-type InputIdentifiers Identifiers|HasIdentifiers|(iterable<non-empty-string>)|non-empty-string
 * @implements IteratorAggregate<non-empty-string>
 */
final readonly class Identifiers implements Countable, IteratorAggregate, JsonSerializable
{
    private const string REGEXP_IDENTIFIER = '/^
        (?!.*[._-]{2})             # disallow consecutive dots or underscore anywhere
        (?!.*[._-]$)               # disallow ending with a dot or underscore
        (?!^[._-])                 # disallow starting with a dot or underscore
        [a-z0-9]                   # first character must be a letter or a digit
        (?:[a-z0-9._-]*[a-z0-9])?  # middle optional, ending with letter or digit
    $/ix';

    /** @var list<non-empty-string> */
    private array $items;

    /**
     * @param InputIdentifiers ...$items
     *
     * @throws TemporalException
     */
    public function __construct(Identifiers|HasIdentifiers|iterable|string ...$items)
    {
        $found = [];
        foreach ($items as $item) {
            foreach (self::filterIdentifiers($item) as $value) {
                $found[$value] = $value;
            }
        }

        $this->items = array_values($found);
    }

    /**
     * @param InputIdentifiers $data
     *
     * @throws TemporalException
     *
     * @return list<non-empty-string>
     */
    private static function filterIdentifiers(Identifiers|HasIdentifiers|iterable|string $data): array
    {
        if ($data instanceof HasIdentifiers) {
            return $data->identifiers->all();
        }

        if ($data instanceof self) {
            return $data->all();
        }

        $sanitize = static function (mixed $value): string {
            is_string($value) || throw TemporalException::dueToInvalidIdentifier($value);
            $value = trim($value);
            1 === preg_match(self::REGEXP_IDENTIFIER, $value) || throw TemporalException::dueToInvalidIdentifier($value);

            return $value;
        };

        if (is_string($data)) {
            $data = [$data];
        }

        $filteredData = [];
        foreach ($data as $value) {
            $filteredData[] = $sanitize($value);
        }

        return $filteredData;
    }

    /**
     * @throws TemporalException
     */
    public static function fromCommaSeparated(string $value): self
    {
        $value = trim($value);
        $list = array_map(trim(...), '' === $value ? [] : explode(',', $value));
        $filtered = array_filter($list, fn (string $value): bool => '' !== $value);

        return $list === $filtered
            ? new self(...$filtered)
            : throw new TemporalException('The submitted value `'.$value.'` contains invalid Identifiers.');
    }

    public function count(): int
    {
        return count($this->items);
    }

    /**
     * @return Iterator<non-empty-string>
     */
    public function getIterator(): Iterator
    {
        yield from $this->items;
    }

    /**
     * @return list<non-empty-string>
     */
    public function jsonSerialize(): array
    {
        return $this->items;
    }

    /**
     * @return list<non-empty-string>
     */
    public function all(): array
    {
        return $this->items;
    }

    public function isEmpty(): bool
    {
        return [] === $this->items;
    }

    public function equals(HasIdentifiers|Identifiers $other): bool
    {
        $otherLabels = $other instanceof HasIdentifiers ? $other->identifiers->items : $other->items;

        return [] === array_diff($this->items, $otherLabels);
    }

    public function has(string ...$values): bool
    {
        foreach ($values as $value) {
            if (!in_array($value, $this->items, true)) {
                return false;
            }
        }

        return [] !== $this->items;
    }

    /**
     * @return ?non-empty-string
     */
    public function primary(): ?string
    {
        return $this->nth(0);
    }

    /**
     * @throws TemporalException If the offset is out of range.
     *
     * @return non-empty-string
     */
    public function get(int $offset): string
    {
        return $this->nth($offset) ?? throw TemporalException::dueToInvalidOffset($offset, self::class);
    }

    /**
     * Returns the label at the given position, or null if it does not exist.
     *
     * Supports negative offsets, where -1 refers to the last label.
     *
     * @return ?non-empty-string
     */
    public function nth(int $offset): ?string
    {
        $count = count($this->items);
        if ($offset < 0) {
            $offset = $count + $offset;
        }

        return $this->items[$offset] ?? null;
    }

    /**
     * @throws TemporalException
     */
    public function only(string ...$labels): self
    {
        $data = array_filter($this->items, fn (string $label): bool => in_array($label, $labels, true));

        return $data === $this->items ? $this : new self($data);
    }

    /**
     * @throws TemporalException
     */
    public function except(string ...$labels): self
    {
        $data = array_filter($this->items, fn (string $label): bool => !in_array($label, $labels, true));

        return $data === $this->items ? $this : new self($data);
    }

    public function asCommaSeparated(): string
    {
        return implode(',', $this->items);
    }

    public function sorted(Direction $direction = Direction::Ascending): self
    {
        $data = $this->items;

        Direction::Ascending === $direction
                ? sort($data, SORT_STRING)
                : rsort($data, SORT_STRING);

        return $data === $this->items ? $this : new self($data);
    }

    /**
     * @param InputIdentifiers ...$others
     *
     * @throws TemporalException
     */
    public function merge(Identifiers|HasIdentifiers|iterable|string ...$others): self
    {
        $found = array_fill_keys($this->items, 1);
        foreach ($others as $other) {
            foreach (self::filterIdentifiers($other) as $value) {
                $found[$value] = 1;
            }
        }

        return new self(array_keys($found));
    }

    /**
     * @return array{0: array{identifiers: list<non-empty-string>}, 1: array{}}
     */
    public function __serialize(): array
    {
        return [['identifiers' => $this->items], []];
    }

    /**
     * @param array{0: array{identifiers: list<non-empty-string>}, 1: array{}} $data
     *
     * @throws TemporalException
     */
    public function __unserialize(array $data): void
    {
        [$properties] = $data;
        $this->items = self::filterIdentifiers($properties['identifiers']);
    }
}
