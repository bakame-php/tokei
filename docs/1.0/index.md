---
layout: default
title: Installation
---

# Installation

## System Requirements

**PHP >= 8.4** is required but the latest stable version of PHP is recommended.

## Installation

**Tokei** is available on [Packagist](https://packagist.org/packages/bakame/tokei) and can be installed using [Composer](https://getcomposer.org/):

~~~
composer require bakame/tokei
~~~

You **MAY** need:

- The `ext-intl` extension or use a polyfill for `IntlDateFormatter` to be able to get the locale string version of the time.