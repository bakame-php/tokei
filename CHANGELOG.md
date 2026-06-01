# Changelog

All Notable changes to `bakame/tokei` will be documented in this file.

## [Next](https://github.com/bakame-php/stackwatch/compare/0.1.0...main) - TBD

### Added

- `IntervalSet::each`
- `IntervalSet::transform`
- `Interval::fromFormat`
- `Duration::fromDateInterval`
- `Duration::fromFormat`
- `Duration::format`
- `Duration::increase`
- `Duration::decrease`
- `Duration::isZero`
- `Time::fromDateTime`
- `Time::toDateTime`
- `Time::fromFormat`
- `Time::format`
- `Time::fromOffset`
- `Time::toOffset`
- `Time::shift`
- `Time::utc`
- `LocaleTimeFormatter`to improve time string localization using `ext-intl`
- `IntervalFormat::encode` and `IntervalFormat::decode` to improve `Interval` encoding and decoding from and to string representation.
- `DurationFormat::encode` and `DurationFormat::decode` to improve `Duration` encoding and decoding from and to string representation.
- `TimeFormat` added to improve `Time` encoding and decoding from and to string representation.
- `Order` to unify sorting across the package
- `Rounding` to unify rounding across the package
- `LocaleVerbosity` to allow fine-grained locale string representation using by `LocaleTimeFormatter`

### Fixed

- `IntervalSet::union` now accepts `Interval` and/or `IntervalSet` as arguments to compute the union between sets/intervals
- `IntervalSet::difference` edge cases when dealing with collapsed or circular Intervals.
- `Time::toLocaleString` accepts timezone string identifier as well as fully instantiated `DateTimeZone` instances.
- `Time::toLocaleString` improves timezone handling, the time is no longer affected by the timezone shift.
- **BC BREAK:** `Duration::format` using Timer format will always output the hours parts with at least two digits previously for hours below 10 one digit was used.
- **BC BREAK:** Renamed method suffixe "Clock" to "Timer" which is a better description for `Duration`
- **BC BREAK:** `Duration::of` no longer accepts negative integer use `negated()` of `fromFormat`.
- **BC BREAK:** `Interval::lasting` signature parameter order.
- **BC BREAK:** `Interval::shiftBound` signature parameter order.
- **BC BREAK:** `Unit` enum now only exposes the `inMicroseconds` method all other methods are moved to an internal `UnitTransformer` class.
- **BC BREAK:** `Time::now` has a new timezone argument which is mandatory.

### Deprecated

- None

### Removed

- **BC BREAK:** `Duration::toClockFormat` is removed and replaced by `Duration::format` with the `DurationFormat::Timer` argument
- **BC BREAK:** `Duration::toIso8601` is removed and replaced by `Duration::format` with the `DurationFormat::Iso8601` argument
- **BC BREAK:** `Duration::toCompact` is removed and replaced by `Duration::format` with the `DurationFormat::Compact` argument
- **BC BREAK:** `Duration::increment` is removed use `Duration::increase` instead
- **BC BREAK:** `Duration::isEmpty` is removed use `Duration::isZero` instead
- **BC BREAK:** `Interval::fromIso8601` is removed and replaced by `Interval::fromFormat` with the `IntervalFormat::Iso8601` argument
- **BC BREAK:** `SubSecondDisplay` is removed with no remplacement use rounding with the `Rounding::Floor` mode
- **BC BREAK:** `truncateTo` is removed use `roundTo` instead with the new `Rounding:Floor` mode
- **BC BREAK:** `Time::add` is removed use `Time::shift` instead
- **BC BREAK:** `Time::fromDate` is removed use `Time::fromDateTime` instead
- **BC BREAK:** `Time::fromUnitOfDay` is removed use `Time::fromOffset` instead
- **BC BREAK:** `Time::toUnitOfDay` is removed use `Time::toOffset` instead
- **BC BREAK:** `Time::toString` is removed use `Time::format` instead
- **BC BREAK:** `IntervalSet::sorted` argument was a string or PHP8.6 `SorDirection` enum is changed to using the `Order` 

## [0.1.0 - asagao](https://github.com/bakame-php/tokei/releases/tag/0.1.0) - 2026-05-27

**Initial release!**
