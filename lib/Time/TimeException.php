<?php

declare(strict_types=1);

namespace Time;

use Exception;

use function class_exists;

use const PHP_VERSION_ID;

if (PHP_VERSION_ID < 80600 && !class_exists('Time\TimeException')) {
    class TimeException extends Exception
    {
    }
}
