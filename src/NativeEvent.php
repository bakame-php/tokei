<?php

declare(strict_types=1);

namespace Bakame\Tokei;

use DateTimeImmutable;

final readonly class NativeEvent
{
    public function __construct(
        public DateTimeImmutable $at,
        public Identifiers $identifiers,
    ) {
    }
}
