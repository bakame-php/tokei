<?php

if (PHP_VERSION_ID < 80600 && !enum_exists('SortDirection', false)) {
    spl_autoload_register(static function (string $class): void {
        if ('SortDirection' === $class) {
            require __DIR__ . '/lib/SortDirection.php';
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
