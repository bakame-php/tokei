<?php

declare(strict_types=1);

namespace Bakame\Tokei;

use ArrayAccess;
use TypeError;
use ValueError;

use function array_key_exists;
use function is_int;
use function is_string;

/**
 * @implements ArrayAccess<mixed, int|Duration>
 */
final readonly class DivisionResult implements ArrayAccess
{
    public function __construct(
        public int $quotient,
        public Duration $remainder,
    ) {
    }

    /**
     * @return array{0: int, 1: Duration}
     */
    private function asTuple(): array
    {
        return [$this->quotient, $this->remainder];
    }

    public function offsetExists(mixed $offset): bool
    {
        return (is_int($offset) || is_string($offset))
            && array_key_exists($offset, $this->asTuple());
    }

    /**
     * @return int|Duration
     */
    public function offsetGet(mixed $offset): mixed
    {
        is_int($offset) || is_string($offset) || throw new TypeError('The offset must be a string or an integer.');

        return $this->asTuple()[$offset] ?? throw new ValueError('Unable to retrieve the value of $offset; only 0 and 1 are allowed for '.  self::class);
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new TokeiException(self::class.' is immutable');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new TokeiException(self::class.' is immutable');
    }
}
