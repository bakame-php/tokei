<?php

declare(strict_types=1);

namespace Bakame\Tokei;

use Countable;
use IteratorAggregate;
use JsonSerializable;

/**
 * @template-covariant TItem
 *
 * @template-extends IteratorAggregate<non-negative-int, TItem>
 */
interface TemporalSet extends Countable, IteratorAggregate, JsonSerializable
{
    /**
     * @return list<TItem>
     */
    public function all(): array;

    public function isEmpty(): bool;

    /**
     * @throws TemporalException If the offset is out of range.
     *
     * @return TItem
     */
    public function get(int $offset): mixed;

    /**
     * Returns the item at the given position, or null if it does not exist.
     *
     * Supports negative offsets, where -1 refers to the last task.
     *
     * @return ?TItem
     */
    public function nth(int $offset): mixed;

    /**
     * @return ?TItem
     */
    public function first(): mixed;

    /**
     * @return ?TItem
     */
    public function last(): mixed;

    /**
     * @param callable(TItem, int=): bool $predicate
     *
     * @return ?TItem
     */
    public function firstMatching(callable $predicate): mixed;

    /**
     * @param callable(TItem, int=): bool $predicate
     *
     * @return ?TItem
     */
    public function lastMatching(callable $predicate): mixed;

    /**
     * Iterates over all items in this set.
     *
     * The callback receives the current item and its index.
     * If the callback returns false, iteration stops immediately.
     *
     * @param callable(TItem, int=): bool $predicate
     *
     * @return bool True if the predicate is true at least once; false otherwise.
     */
    public function any(callable $predicate): bool;

    /**
     * Iterates over all items in this set.
     *
     * The callback receives the current item and its index.
     * If the callback returns false, iteration stops immediately.
     *
     * @param callable(TItem, int=): bool $predicate
     *
     * @return bool @return bool True if all items satisfy the predicate; false otherwise.
     */
    public function every(callable $predicate): bool;

    /**
     * @template TReduceInitial
     * @template TReduceReturnType
     *
     * @param callable(TReduceInitial|TReduceReturnType, TItem, int=): TReduceReturnType $callback
     * @param TReduceInitial $initial
     *
     * @return TReduceInitial|TReduceReturnType
     */
    public function reduce(callable $callback, mixed $initial = null): mixed;

    /**
     * Iterates over all items in this set.
     *
     * The callback receives the current item and its index.
     * If the callback returns false, iteration stops immediately.
     *
     * @param callable(TItem, int=): mixed $callback
     *
     * @return bool True if iteration completed, false if it was stopped early by the callback.
     */
    public function each(callable $callback): bool;

    /**
     * @template TValue
     *
     * @param callable(TItem, int=): TValue $callback
     *
     * @return iterable<TValue>
     */
    public function map(callable $callback): iterable;
}
