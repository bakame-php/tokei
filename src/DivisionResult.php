<?php

declare(strict_types=1);

namespace Bakame\Tokei;

use ArrayAccess;
use Override;
use ValueError;

/**
 * @implements ArrayAccess<non-negative-int, Duration|int>
 */
final readonly class DivisionResult implements ArrayAccess
{
    public function __construct(
        public int $quotient,
        public Duration $remainder,
    ) {
    }

    public function offsetExists(mixed $offset): bool
    {
        return 1 === $offset || 0 === $offset;
    }

    /**
     * @return ($offset is 0 ? int : Duration)
     */
    #[Override]
    public function offsetGet(mixed $offset): Duration|int
    {
        return match ($offset) {
            0 => $this->quotient,
            1 => $this->remainder,
            default => throw new ValueError('Only offsets 0 and 1 are supported.'),
        };
    }

    #[Override]
    public function offsetSet(mixed $offset, mixed $value): never
    {
        throw TokeiError::dueToImmutability(self::class);
    }

    #[Override]
    public function offsetUnset(mixed $offset): never
    {
        throw TokeiError::dueToImmutability(self::class);
    }
}
