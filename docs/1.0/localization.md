---
layout: default
title: Localization
---

# Localization

## Requirements

Localized string representations of `Time` and `Duration` require ICU support.

Depending on your PHP version, ICU support is provided by either the `ext-intl` extension or the `symfony/polyfill-intl-icu` package:

- **PHP 8.4:** `symfony/polyfill-intl-icu` **>= v1.34** is required, even when `ext-intl` is installed.
- **PHP 8.5 and later:** `ext-intl` provides the required functionality; `symfony/polyfill-intl-icu` is not required.

If neither `ext-intl` nor a supported version of `symfony/polyfill-intl-icu` is available, the localized formatters cannot be used.

## Duration

```php
use Bakame\Tokei\DurationFormatter;use Bakame\Tokei\ListWidth;

DurationFormatter::__construct(string $locale, ListWidth $listWidth = ListWidth::Wide);
DurationFormatter::format(Duration $duration): string
```

Use `DurationFormatter::format()` to obtain a localized string representation of a duration.

The duration is always formatted as an **absolute value**. The sign of the duration is therefore ignored.
For example, a negative duration is not formatted using relative expressions such as `in ...` or `... ago`.

```php
use Bakame\Tokei\Duration;
use Bakame\Tokei\DurationFormatter;

$duration = Duration::of(hours: 3, seconds: 25, microseconds: 134);

$formatter = new DurationFormatter('tr');

echo $formatter->format($duration);
// 3 saat, 25 saniye ve 134 mikrosaniye
```

The locale used by the formatter is available through the `locale` property:

```php
use Bakame\Tokei\DurationFormatter;

$formatter = new DurationFormatter('fr');

echo $formatter->locale;
// 'fr'
echo $formatter->listWidth->name;
 // 'Wide'
```

## Time

```php
use Bakame\Tokei\TimeFormatter;

TimeFormatter::__construct(
    string $locale,
    DateTimeZone|string $timezone = 'UTC',
    LocaleVerbosity $verbosity = LocaleVerbosity::Medium
);

TimeFormatter::format(
    Time|DateTimeInterface $time,
    DateTimeZone|string|null $timezone = null
): string;
```

Use `TimeFormatter::format()` to obtain a localized string representation of a `Time` or `DateTimeInterface`.

The formatter's timezone is used when formatting a `Time` unless another timezone is explicitly provided to `format()`.
When a `DateTimeInterface` is provided, its own timezone information is used.

The verbosity controls the amount of information included in the formatted time.

```php
use Bakame\Tokei\LocaleVerbosity;
use Bakame\Tokei\Time;
use Bakame\Tokei\TimeFormatter;

$time = Time::at(
    hour: 10,
    minute: 30,
    second: 15,
    microsecond: 123456,
);

$formatter = new TimeFormatter('en-US');

echo $formatter->format($time);
// 10:30:15 AM

$formatter = new TimeFormatter(
    locale: 'de-DE',
    verbosity: LocaleVerbosity::Full,
);

echo $formatter->format($time, 'Africa/Nairobi');
// 10:30:15 Ostafrikanische Zeit
```

The formatter's configuration is available through its public properties:

```php
use Bakame\Tokei\LocaleVerbosity;
use Bakame\Tokei\TimeFormatter;

$formatter = new TimeFormatter(
    locale: 'de-DE',
    verbosity: LocaleVerbosity::Full,
);

echo $formatter->locale;
// 'de-DE'

echo $formatter->verbosity->name;
// 'Full'

echo $formatter->timezone->getName();
// 'UTC'
``````