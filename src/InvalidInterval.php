<?php

declare(strict_types=1);

namespace Bakame\Tokei;

use Throwable;

class InvalidInterval extends TimeException
{
    public static function dueToMalformedNotation(string $notation, IntervalNotation $source, ?Throwable $previous = null): self
    {
        return new self('"'.$notation.'" is an invalid or unsupported '.$source->name.' notation.', previous: $previous);
    }
}
