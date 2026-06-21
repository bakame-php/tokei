![tokei](.github/tokei-logo.jpg?raw=true)

# Tokei

[![Author](http://img.shields.io/badge/author-@nyamsprod-blue.svg?style=flat-square)](https://phpc.social/@nyamsprodd)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE)
[![Build](https://github.com/bakame-php/tokei/workflows/build/badge.svg)](https://github.com/bakame-php/tokei/actions?query=workflow%3A%22build%22)
[![Latest Version](https://img.shields.io/github/release/bakame-php/tokei.svg?style=flat-square)](https://github.com/bakame-php/tokei/releases)
[![Total Downloads](https://img.shields.io/packagist/dt/bakame/tokei.svg?style=flat-square)](https://packagist.org/packages/bakame/tokei)
[![Sponsor development of this project](https://img.shields.io/badge/sponsor%20this%20package-%E2%9D%A4-ff69b4.svg?style=flat-square)](https://github.com/sponsors/nyamsprod)

**Tokei** (pronounced: [to̞ke̞ː] or [tokeː]) is a lightweight domain-focused set of immutable value objects for representing
and operating on time, durations, including, circular 24-hour intervals, and interval sets, offering
expressive temporal modeling without timezone handling.

The framework-agnostic package offers a consistent and expressive way to work with temporal values in a safe
and predictable manner.

```php
use Bakame\Tokei\Duration;
use Bakame\Tokei\DurationFormat;
use Bakame\Tokei\Time;

$target = Duration::of(hours: 7, minutes: 33);
$alreadyDone = Duration::of(hours: 5, minutes: 17);
$remaining = $target->sum($alreadyDone->negated());
$startedNewShiftAt = Time::at(hour: 21, minute: 31);
$shouldStopAt = $startedNewShiftAt->shift($remaining);

echo "I have to work ", $target->format(DurationFormat::Compact), " today", PHP_EOL;
echo "I have already worked for ", $alreadyDone->format(DurationFormat::Compact), PHP_EOL;
echo "I still have to work ", $remaining->format(DurationFormat::Compact), PHP_EOL;
echo "If I start working again at ", $startedNewShiftAt->format(), PHP_EOL;
echo "I will end today's shit at ", $shouldStopAt->format(), PHP_EOL;

// Gives the following:

/**
 * I have to work 7h33m today
 * I have already worked for 5h17m
 * I still have to work 2h16m
 * If I start working again at 21:31:00
 * I will end today's shit at 23:47:00
 */
```

## Installation

~~~
composer require bakame/tokei
~~~

You need:

- **PHP >= 8.4** but the latest stable version of PHP is recommended
- **The library does not support 32bit PHP**
- to be able to get the locale string version of the time you need the `ext-intl` extension or use a polyfill for `IntlDateFormatter`.

## Documentation

Full documentation can be found at [https://bakame-php.github.io/tokei](//bakame-php.github.io/tokei)

## Testing

The library has:

- a [PHPUnit](https://phpunit.de) test suite.
- a coding style compliance test suite using [PHP CS Fixer](https://cs.symfony.com/).
- a code analysis compliance test suite using [PHPStan](https://github.com/phpstan/phpstan).

To run the tests, run the following command from the project folder.

```bash
composer test
```

## Contributing

Contributions are welcome and will be fully credited. Please see [CONTRIBUTING](.github/CONTRIBUTING.md) and [CONDUCT](.github/CODE_OF_CONDUCT.md) for details.

## Security

If you discover any security related issues, please email nyamsprod@gmail.com instead of using the issue tracker.

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Credits

- [ignace nyamagana butera](https://github.com/nyamsprod)
- [All Contributors](https://github.com/bakame-php/tokei/graphs/contributors)
