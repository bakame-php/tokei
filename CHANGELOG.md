# Changelog

All Notable changes to `bakame/tokei` will be documented in this file.

## [Next](https://github.com/bakame-php/stackwatch/compare/0.1.0...main) - TBD

### Added

- `IntervalSet::each`
- `Duration::fromDateInterval`
- `DurationFormat` Enum
- `Duration::format`
- `TimeFormatLength` to `Time::toLocaleString` method to allow fine-grained locale string representation supported by `intl` extension

### Fixed

- `IntervalSet::union` now accept `Interval` and/or `IntervalSet` as arguments to compute the union between sets/intervals
- `IntervalSet::difference` edge cases when dealing with collapsed or circular Intervals.
- `Time::toLocaleString` and `Time::now` accepts timezone string identifier as well as fully instantiated `DateTimeZone` instances.

### Deprecated

- None

### Removed

- **BC BREAK:** `Duration::toClockFormat` is removed and replaced by `Duration::format`
- **BC BREAK:** `Duration::toIso8601` is removed and replaced by `Duration::format`
- **BC BREAK:** `Duration::toCompact` is removed and replaced by `Duration::format`

## [0.1.0 - asagao](https://github.com/bakame-php/tokei/releases/tag/0.1.0) - 2026-05-27

**Initial release!**
