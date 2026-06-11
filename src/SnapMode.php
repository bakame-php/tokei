<?php

declare(strict_types=1);

namespace Bakame\Tokei;

enum SnapMode
{
    case Floor;
    case Nearest;
    case Ceil;
}
