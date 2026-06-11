<?php

declare(strict_types=1);

namespace Bakame\Tokei;

use Exception;
use Throwable;

class TokeiException extends Exception
{
    final public function __construct(string $message = '', int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    public static function dueToInvalidOffset(int|string $offset, string $className): static
    {
        return new static('Invalid offset ('.$offset.') given to '.$className.'.');
    }
}
