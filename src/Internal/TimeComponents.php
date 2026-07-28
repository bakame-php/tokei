<?php

declare(strict_types=1);

namespace Bakame\Tokei\Internal;

/**
 * @internal
 */
final readonly class TimeComponents
{
    public function __construct(
        public int $hour,
        public int $minute,
        public int $second,
        public int $nanosecond,
    ) {
    }
}
