<?php

declare(strict_types=1);

namespace Bakame\Tokei;

/**
 * Verbosity level of the time representation.
 */
enum TimeVerbosity
{
    case Short;
    case Medium;
    case Long;
    case Full;
}
