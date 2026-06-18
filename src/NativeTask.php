<?php

declare(strict_types=1);

namespace Bakame\Tokei;

final readonly class NativeTask
{
    public function __construct(
        public NativeInterval $interval,
        public Identifiers $identifiers
    ) {
    }
}
