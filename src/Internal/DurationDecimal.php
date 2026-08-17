<?php

declare(strict_types=1);

namespace Bakame\Tokei\Internal;

use Bakame\Tokei\Unit;

final readonly class DurationDecimal
{
    public function __construct(
        public int|float $value,
        public Unit $unit,
    ) {
    }
}
