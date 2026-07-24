<?php

if (PHP_VERSION_ID < 80600 && !enum_exists('SortDirection')) {
    spl_autoload_register(static function (string $class): void {
        if ('SortDirection' === $class) {
            require __DIR__ . '/polyfill/lib/SortDirection.php';
        }
    });
}

if (PHP_VERSION_ID < 80600 && !class_exists('Time\Duration')) {
    spl_autoload_register(static function (string $class): void {
        $path = match ($class) {
            'Time\\Duration' => __DIR__ . '/polyfill/lib/Time/Duration.php',
            'Time\\TimeException' => __DIR__ . '/polyfill/lib/Time/TimeException.php',
            default => null,
        };

        if (null !== $path) {
            require $path;
        }
    });
}

spl_autoload_register(static function (string $class): void {
    if (!str_starts_with($class, 'Bakame\Tokei\\')) {
        return;
    }

    $file = __DIR__ . '/src/' . str_replace('\\', '/', substr($class, 13)).'.php';
    if (is_readable($file)) {
        require $file;
    }
});
