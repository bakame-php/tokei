<?php

declare(strict_types=1);

namespace Bakame\Tokei;

enum DurationFormat
{
    /**
     * Displays the duration using ISO8601 Duration string representation.
     */
    case Iso8601;
    /**
     * Displays the duration as a digital timer: HH:MM:SS.
     */
    case Timer;
    /**
     * Displays the duration using compact units: 1h30m15s.
     */
    case Compact;
    /**
     * Displays the duration using a single number and its associated units: 2 minutes.
     */
    case SingleUnit;
}
