<?php

declare(strict_types=1);

namespace Bakame\Tokei;

use DateInterval;
use DateTimeImmutable;

final readonly class NativeInterval
{
    public function __construct(
        public DateTimeImmutable $start,
        public DateTimeImmutable $end,
    ) {
        $start <= $end || throw new TimeException('Start date must be less than end date');
    }

    public function duration(): DateInterval
    {
        return $this->start->diff($this->end);
    }
}
