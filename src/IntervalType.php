<?php

declare(strict_types=1);

namespace Bakame\Tokei;

enum IntervalType
{
    case Linear;
    case Overflow;
    case Circular;
    case Collapsed;
}
