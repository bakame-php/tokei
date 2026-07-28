<?php

declare(strict_types=1);

namespace Bakame\Tokei;

enum Unit
{
    case Week;
    case Day;
    case Hour;
    case Minute;
    case Second;
    case Millisecond;
    case Microsecond;
    case Nanosecond;
}
