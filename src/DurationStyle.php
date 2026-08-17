<?php

declare(strict_types=1);

namespace Bakame\Tokei;

enum DurationStyle
{
    /**
     * Decompose into conventional units.
     */
    case Decomposed;

    /**
     * Express the duration using the largest suitable unit,
     * allowing fractional values.
     */
    case LargestUnit;

    /**
     * Express the entire duration using a single unit,
     * choosing the largest unit that preserves the original precision.
     */
    case TotalUnit;
}
