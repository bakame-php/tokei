<?php

declare(strict_types=1);

namespace Bakame\Tokei;

enum TimeFormat
{
    /**
     * Displays the time as a digital clock: HH:MM:SS.MMMM.
     */
    case Clock;
    /**
     * Displays the time using compact units: 1h30m15s.
     */
    case Compact;
}
