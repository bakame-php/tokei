<?php

declare(strict_types=1);

namespace Bakame\Tokei;

/**
 * Defines the supported representations of a duration.
 *
 * A duration format determines how a Duration is formatted into a string
 * and, where supported, how a string representation is parsed into a Duration.
 *
 * The supported representations are:
 *
 * - ISO 8601 extended duration notation
 * - digital timer notation
 * - compact unit notation
 * - largest-unit notation using the largest suitable unit
 * - total-unit notation using a single unit that preserves precision
 */
enum DurationFormat
{
    /**
     * Represents the extended ISO 8601 duration representation,
     * sign and fractional seconds are therefore supported: -PT3H26.5S.
     */
    case Iso8601;

    /**
     * Represents a digital timer notation,
     * allowing fractional part: HH:MM:SS.
     */
    case Timer;

    /**
     * Represents a duration using compact units: 1h30m15s.
     */
    case Compact;

    /**
     * Represents a duration using the largest suitable unit,
     * allowing fractional values: 2.5h.
     */
    case LargestUnit;

    /**
     * Represents a duration using a single unit,
     * choosing the largest unit that preserves the original precision: 192ms.
     */
    case TotalUnit;
}
