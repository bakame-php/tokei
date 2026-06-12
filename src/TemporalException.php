<?php

declare(strict_types=1);

namespace Bakame\Tokei;

use function get_debug_type;

class TemporalException extends TokeiException
{
    public static function dueToInvalidIdentifier(mixed $value): self
    {
        $message = !is_string($value)
            ? 'The Identifier value must be a non-empty string; '.get_debug_type($value).' given.'
            : 'The identifier value must start with a letter or a digit and only contain letters, digits, point or underscores; '.$value.' given.';

        return new self($message);
    }
}
