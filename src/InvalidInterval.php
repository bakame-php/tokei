<?php

declare(strict_types=1);

namespace Bakame\Tokei;

use Throwable;

class InvalidInterval extends TimeException
{
    public static function dueToMalformedFormat(string $format, IntervalFormat $source, ?Throwable $previous = null): self
    {
        return new self('"'.$format.'" is an invalid or unsupported '.$source->name.' format.', previous: $previous);
    }
}
