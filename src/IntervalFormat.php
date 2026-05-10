<?php

declare(strict_types=1);

namespace Bakame\Tokei;

enum IntervalFormat
{
    case Iso8601StartDuration;
    case Iso8601DurationEnd;
    case Iso8601StartEnd;
    case Iso80000;
    case Bourbaki;
}
