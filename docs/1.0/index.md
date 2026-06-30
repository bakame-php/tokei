---
layout: default
title: Installation
---

# Installation

## System Requirements

**PHP >= 8.4** is required but the latest stable version of PHP is recommended.

## Installation

### Using Composer

**Tokei** is available on [Packagist](https://packagist.org/packages/bakame/tokei) and can be installed using [Composer](https://getcomposer.org/):

~~~
composer require bakame/tokei
~~~

You **MAY** need:

- The `ext-intl` extension or use a polyfill for `IntlDateFormatter` to be able to get the locale string version of the time.

### Manual installation

You can also use `Tokei` without using Composer by downloading the library on Github.

1. Visit [the releases page](https://github.com/bakame-php/tokei/releases) of the project.
2. Find the release of `Tokei` for your version of PHP.
3. Click the **Source Code** link for preferred compression format.

The library is compatible with any [PSR-4](http://www.php-fig.org/psr/psr-4/) compatible autoloader.

Also, `Tokei` comes bundled with its own autoloader script `autoload.php` located in the root directory.

```php
use Bakame\Tokei\Duration;
use Bakame\Tokei\Time;

require '/path/to/tokeu/autoload.php';

// Your script starts here
// ...
```

where `path/to/tokei` represents the path where the library was extracted.