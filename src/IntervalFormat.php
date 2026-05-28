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

    /**
     * @see https://en.wikipedia.org/wiki/Interval_(mathematics)#Notations_for_intervals
     * @see https://en.wikipedia.org/wiki/ISO_31-11
     *
     * @throws InvalidTime
     *
     * @return non-empty-string
     */
    public function format(
        Interval $interval,
        SubSecondDisplay $subSecondDisplay = SubSecondDisplay::Auto
    ): string {
        return match ($this) {
            IntervalFormat::Iso8601StartDuration => $interval->start->toString($subSecondDisplay).'/'.$interval->duration->format(),
            IntervalFormat::Iso8601DurationEnd => $interval->duration->format().'/'.$interval->end->toString($subSecondDisplay),
            IntervalFormat::Iso8601StartEnd => $interval->start->toString($subSecondDisplay).'/'.$interval->end->toString($subSecondDisplay),
            IntervalFormat::Iso80000 => '['.$interval->start->toString($subSecondDisplay).','.$interval->end->toString($subSecondDisplay).')',
            IntervalFormat::Bourbaki => '['.$interval->start->toString($subSecondDisplay).','.$interval->end->toString($subSecondDisplay).'[',
        };
    }
}
