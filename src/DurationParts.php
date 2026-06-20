<?php

declare(strict_types=1);

namespace Bakame\Tokei;

final readonly class DurationParts
{
    public function __construct(
        public int $hours,
        public int $minutes,
        public int $seconds,
        public int $microseconds,
        public int $sign,
    ) {
    }

    public static function parse(int $value): self
    {
        $sign = $value <=> 0 ;
        $microseconds = -1 === $sign ? -$value : $value;
        $hours = UnitTransformer::whole($microseconds, Unit::Hour);
        $microseconds = UnitTransformer::remainder($microseconds, Unit::Hour);
        $minutes = UnitTransformer::whole($microseconds, Unit::Minute);
        $microseconds = UnitTransformer::remainder($microseconds, Unit::Minute);
        $seconds = UnitTransformer::whole($microseconds, Unit::Second);
        $microseconds = UnitTransformer::remainder($microseconds, Unit::Second);

        return new self(
            hours: $hours,
            minutes: $minutes,
            seconds: $seconds,
            microseconds: $microseconds,
            sign: $sign,
        );
    }

    public function build(): int
    {
        $value = UnitTransformer::toMicroseconds($this->hours, Unit::Hour)
            + UnitTransformer::toMicroseconds($this->minutes, Unit::Minute)
            + UnitTransformer::toMicroseconds($this->seconds, Unit::Second)
            + $this->microseconds;

        return $value * $this->sign;
    }
}
