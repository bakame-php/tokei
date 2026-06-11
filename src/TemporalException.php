<?php

declare(strict_types=1);

namespace Bakame\Tokei;

use function get_debug_type;

class TemporalException extends TokeiException
{
    public static function dueToInvalidIdentifier(mixed $value): self
    {
        $message = 'string' !== ($type = get_debug_type($value))
            ? 'Identifier values must be non-empty strings; '.$type.' given.'
            : 'Identifier values must be non-empty strings.';

        return new self($message);
    }
}
