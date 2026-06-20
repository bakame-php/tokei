<?php

declare(strict_types=1);

namespace Bakame\Tokei;

enum DurationFormat
{
    case Iso8601;
    case Compact;
    case Timer;
}
