<?php

declare(strict_types=1);

namespace Bakame\Tokei;

enum RoundingMode
{
    case Floor;
    case Nearest;
    case Ceil;
}
