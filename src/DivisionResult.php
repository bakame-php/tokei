<?php

declare(strict_types=1);

namespace Bakame\Tokei;

final readonly class DivisionResult
{
    public function __construct(
        public int $quotient,
        public Duration $remainder,
    ) {
    }

    /**
     * @return array{0: int, 1: Duration}
     */
    public function asTuple(): array
    {
        return [$this->quotient, $this->remainder];
    }
}
