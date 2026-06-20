<?php

declare(strict_types=1);

namespace Bakame\Tokei;

final readonly class ChunkResult
{
    public function __construct(
        public int $count,
        public Duration $remainder,
    ) {
    }
}
