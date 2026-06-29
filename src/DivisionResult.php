<?php

declare(strict_types=1);

namespace Bakame\Tokei;

final readonly class DivisionResult
{
    public function __construct(
        public int $factor,
        public Duration $remainder,
    ) {
    }
}
