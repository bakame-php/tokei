# Changelog

All Notable changes to `bakame/tokei` will be documented in this file.

## [Next](https://github.com/bakame-php/stackwatch/compare/0.1.0...main) - TBD

### Added

- `Event`, `Task`, `EventSet`, `TaskSet`, `Identifiers` to work with identified Temporal Values.
- `NativeInterval`, `NativeEvent`, `NativeTask` to allow better conversion to PHP Native Date API.
- `IntervalSet::each`
- `IntervalSet::transform`
- `IntervalSet::chronological`
- `IntervalSet::next`
- `IntervalSet::previous`
- `IntervalSet::nearest`
- `IntervalSet::roundTo`
- `IntervalSet::roundDurationTo`
- `IntervalSet::formatAll`
- `Interval::fromFormat`
- `Interval::roundTo`
- `Interval::roundDurationTo`
- `Duration::fromDateInterval`
- `Duration::fromFormat`
- `Duration::format`
- `Duration::increase`
- `Duration::decrease`
- `Duration::dividedInto`
- `Duration::isZero`
- `Duration::component`
- `Duration::in`
- `Time::fromDateTime`
- `Time::toDateTime`
- `Time::fromFormat`
- `Time::format`
- `Time::sinceMidnight`
- `Time::in`
- `Time::shift`
- `Time::utc`
- `Time::roundTo`
- `LocaleTimeFormatter`to improve time string localization using `ext-intl`
- `SnapMode` to unify rounding
- `SearchMode` to unify search type (linear or circular)
- `LocaleVerbosity` to allow fine-grained locale string representation using by `LocaleTimeFormatter`

### Fixed

- `IntervalSet::every` now returns `true` for empty collection
- `Interval::splitAt` now correctly works on a circular range.
- `IntervalSet::union` now accepts `Interval` and/or `IntervalSet` as arguments to compute the union between sets/intervals
- `IntervalSet::difference` edge cases when dealing with collapsed or circular Intervals.
- `Time::toLocaleString` accepts timezone string identifier as well as fully instantiated `DateTimeZone` instances.
- `Time::toLocaleString` improves timezone handling, the time is no longer affected by the timezone shift.
- **BC BREAK:** `Duration::format` using Timer format will always output the hours parts with at least two digits previously for hours below 10 one digit was used.
- **BC BREAK:** Renamed methods suffixed with "Clock" to "Timer" which is a better description for `Duration`
- **BC BREAK:** `Duration::of` no longer accepts negative integer use `negated()` or `fromFormat`.
- **BC BREAK:** `Interval::lasting` signature parameter order.
- **BC BREAK:** `Interval::shiftBound` signature parameter order.
- **BC BREAK:** `Unit` enum now only exposes the `inMicroseconds` method all other methods are moved to an internal `UnitTransformer` class.
- **BC BREAK:** `Time::now` has a new mandatory timezone argument.

### Deprecated

- None

### Removed

- **BC BREAK:** `Duration::toClockFormat` is removed and replaced by `Duration::format` with the `DurationFormat::Timer` argument
- **BC BREAK:** `Duration::toIso8601` is removed and replaced by `Duration::format` with the `DurationFormat::Iso8601` argument
- **BC BREAK:** `Duration::toCompact` is removed and replaced by `Duration::format` with the `DurationFormat::Compact` argument
- **BC BREAK:** `Duration::increment` is removed use `Duration::increase` instead
- **BC BREAK:** `Duration::isEmpty` is removed use `Duration::isZero` instead
- **BC BREAK:** `Duration::total` is removed and replaced by `Duration::in`
- **BC BREAK:** all `Duration` public properties are removed except for `microseconds` and `sign`
- **BC BREAK:** `Interval::fromIso8601` is removed and replaced by `Interval::fromFormat` with the `IntervalFormat::Iso8601StartDuration` argument
- **BC BREAK:** `Interval::compareDurationTo` is removed use `Duration::compare` instead
- **BC BREAK:** `SubSecondDisplay` is removed with no remplacement use rounding with the `Rounding::Floor` mode
- **BC BREAK:** `truncateTo` is removed use `roundTo` instead with the new `Rounding:Floor` mode
- **BC BREAK:** `Time::add` is removed use `Time::shift` instead
- **BC BREAK:** `Time::fromDate` is removed use `Time::fromDateTime` instead
- **BC BREAK:** `Time::fromUnitOfDay` is removed use `Time::sinceMidnight` instead
- **BC BREAK:** `Time::toUnitOfDay` is removed use `Time::in` instead
- **BC BREAK:** `Time::toString` is removed use `Time::format` instead
- **BC BREAK:** `IntervalSet::sorted` argument was a string or PHP8.6 `SorDirection` enum is changed to only supports PHP8.6 `SorDirection` enum.
- **BC BREAK:** `IntervalSet::allFormatted` is removed use `IntervalSet::formatAll` instead

## [0.1.0 - asagao](https://github.com/bakame-php/tokei/releases/tag/0.1.0) - 2026-05-27

**Initial release!**
