---
layout: default
title: Installation
---

# Installation

## System Requirements

- **PHP >= 8.4** is required but the latest stable version of PHP is recommended.
- `symfony/polyfill-php86` to use PHP's `SortDirection` Enum in older PHP version.

## Installation

**Tokei** is available on [Packagist](https://packagist.org/packages/bakame/tokei) and can be installed using [Composer](https://getcomposer.org/):

~~~
composer require bakame/tokei
~~~

You **MAY** need the `ext-intl` extension or use `symfony/polyfill-intl-icu` **>= v1.34**
to use localized string representations of `Time` and/or the `Duration`.

**On PHP 8.4**, `symfony/polyfill-intl-icu` **>= v1.34** is required, even when `ext-intl` is available.
**On PHP 8.5 and later**, `ext-intl` is sufficient and the polyfill is no longer required.