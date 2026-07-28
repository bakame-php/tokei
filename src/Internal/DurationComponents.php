<?php

declare(strict_types=1);

namespace Bakame\Tokei\Internal;

/**
 * @internal
 */
final readonly class DurationComponents
{
    public function __construct(
        public int $seconds,
        public int $nanoseconds,
    ) {
    }
}
