<?php

declare(strict_types=1);

namespace Bakame\Tokei;

enum RoundingStrategy
{
    case Floor;
    case Nearest;
    case Ceil;
}
