---
layout: default
title: Localization
---

# Localization

## Localize

The `Localize` class provides locale-aware string representations of Tokei temporal values.
It uses ICU locale data to format `Time` and `Duration` instances according to the specified
locale and formatting options, producing human-readable representations adapted to the
target language and conventions.

## Requirements

To work as expected the `Localize` class require ICU support.

Depending on your PHP version, ICU support is provided by either the `ext-intl` extension or the `symfony/polyfill-intl-icu` package:

| PHP version  | Required                                   | Optional                                                        |
|--------------|--------------------------------------------|-----------------------------------------------------------------|
| **PHP 8.5+** | `ext-intl`                                 | `symfony/polyfill-intl-icu` (if the extension is not available) |
| **PHP 8.4**  | `ext-intl` and `symfony/polyfill-intl-icu` | -                                                               |

When needed require at least `symfony/polyfill-intl-icu` **>= v1.34** 

<p class="message-notice"><code>symfony/polyfill-intl-icu</code> only provides the <code>en</code> locale out of the box. If you need support for additional locales, refer to the polyfill documentation for instructions.</p>

If neither `ext-intl` nor a supported version of `symfony/polyfill-intl-icu` is available, `Localize` cannot be used.
Attempting to use it will throw a `TokeiException`.

## Duration

```php
use Bakame\Tokei\Localize;
use Bakame\Tokei\ListWidth;
use Bakame\Tokei\UnitWidth;

Localize::duration(
    Duration $duration,
    string $locale,
    UnitWidth $unitWidth = UnitWidth::Wide,
    ListWidth $listWidth = ListWidth::Wide,
): string
```

Use `Localize::duration()` to obtain a localized string representation of a duration.

The duration is always formatted as an **absolute value**. The sign of the duration is therefore ignored.
For example, a negative duration is not formatted using relative expressions such as `in ...` or `... ago`.

```php
use Bakame\Tokei\Duration;
use Bakame\Tokei\Localize;
use Bakame\Tokei\ListWidth;
use Bakame\Tokei\UnitWidth;

$duration = Duration::of(hours: 3, seconds: 25, microseconds: 134);

echo Localize::duration(duration: $duration, locale: 'tr');
// 3 saat, 25 saniye ve 134 mikrosaniye

echo Localize::duration(
    duration: $duration,
    locale: 'tr',
    unitWidth: UnitWidth::Narrow,
    listWidth: ListWidth::Narrow,
);
// 3s, 25sn, 134 μsn
```

## Time

```php
use Bakame\Tokei\Localize;
use Bakame\Tokei\TimeVerbosity;

Localize::time(
    Time|Even|DateTimeInterface $time,
    string $locale,
    DateTimeZone|string $timezone = 'UTC',
    TimeVerbosity $verbosity = TimeVerbosity::Medium
): string;
```
Use `Localize::time()` to obtain a localized string representation of a `Time` or `DateTimeInterface`.

The `$timezone` argument is used when formatting a `Time`.
When a `DateTimeInterface` is provided, its own timezone information is used.

The verbosity controls the amount of information included in the formatted time.

```php
use Bakame\Tokei\Localize;
use Bakame\Tokei\Time
use Bakame\Tokei\TimeVerbosity;

$time = Time::at(
    hour: 10,
    minute: 30,
    second: 15,
    microsecond: 123456,
);

echo Localize::time(time: $time, locale: 'en-US');
// 10:30:15 AM

echo Localize::time(
    time: $time, 
    locale: 'de-DE',
    timezone: 'Africa/Nairobi',
    verbosity: TimeVerbosity::Full,
);
// 10:30:15 Ostafrikanische Zeit
```