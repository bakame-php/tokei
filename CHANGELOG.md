# Changelog

All Notable changes to `bakame/tokei` will be documented in this file.

## [next - title](https://github.com/bakame-php/tokei/compare/0.3.0...main) - TBD

### Added

- Support for nanoseconds to `Tokei\Duration`
- Support for PHP8.6 `Time\Duration`
- `Tokei\Duration` and `Time\Duration` shares the same API (public properties and public methods)
- `Duration::toNumberString`
- `DurationFormatter`
- `Interval::shift` 
- `IntervalSet::append`, `IntervalSet::totalDuration`
- `TaskSet::shift`, `TaskSet::expand`, `TaskSet::roundTo`, `TaskSet::append`, `TaskSet::totalDuration`
- `EventSet::shift`, `EventSet::append`, `EventSet::replace`, `EventSet::remove`
- `DisplaySign` enum
- `ListWidth` enum
- the package now requires `symfony/polyfill-php86` to use PHP8.6 `SortDirection` Enum

### Fixed

- **BC Break:**  mathematical operation methods of `Duration` use full names in present tense to match `Time\Duration` API
- **BC Break:** `Time::toDateTime` is renamed `Time::today`
- **BC Break:** `Interval::fromLinearSpan*` is marked as internal and removed from Public APU.
- **BC Break:** The `LocaleTimeFormatter` class is renamed `TimeFormatter`.

### Deprecated

- None

### Removed

- **BC Break:** `Duration` public properties are removed use `Duration::$seconds`, `Duration::$nanoseconds` and `Duration::$negative` must be use instead
- **BC Break:** `Duration::toDateInterval` removed with no replacement
- **BC Break:** `Interval::linear*` public properties are removed with no replacement
- **BC Break:** Removed manual autoload script
- **BC Break:** `Interval::add()` and `Interval::sub()` replaced by `Interval::shift()`
- **BC Break:** `IntervalSet::unshift()` used `IntervalSet::append()` instead
- **BC Break:** `IntervalSet::sub()` and `IntervalSet::sub()` used `IntervalSet::shift()` instead
- **BC Break:** `IntervalSet::roundDurationTo()` with no replacement
- **BC Break:** `IntervalSet::push()` use `IntervalSet::append()` instead
- **BC Break:** `IntervalSet::duration()` use `IntervalSet::totalDuration()` instead
- **BC Break:** `IntervalSet::outsideOf()` with no replacement
- **BC Break:** `TaskSet::add()` and `TaskSet::sub()` used `TaskSet::shift()` instead
- **BC Break:** `TaskSet::push()` use `TaskSet::append()` instead
- **BC Break:** `TaskSet::duration()` use `TaskSet::totalDuration()` instead
- **BC Break:** `TaskSet::outsideOf()` with no replacement
- **BC Break:** `EventSet::outside()` with no replacement
- **BC Break:** `EventSet::add()` and `EventSet::sub()` used `EventSet::shift()` instead
- **BC Break:** `EventSet::push()` use `EventSet::append()` instead
- **BC Break:** removed the package `SortDirection` polyfill, the `symfony/polyfill-php86` package is use instead
- **BC Break:** `Time::toLocaleString` use the `TimeFormatter` class directly instead.

## [0.3.0 - chidorisō](https://github.com/bakame-php/tokei/compare/0.2.0...0.3.0) - 2026-07-21

### Added

- **BC BREAK:** `DivisionResult` implements `ArrayAccess`
- `Time::add` and `Time::sub` support variadics for duration argument.

### Fixed

- `Duration::format(DurationFormat::Compact)` for negative duration.
- **BC BREAK:** `TaskSet::union` aligns signature with `IntervalSet::union`
- **BC BREAK:** `TaskSet::intersect` aligns signature with `IntervalSet::intersect`
- **BC BREAK:** `TaskSet::difference` aligns signature with `IntervalSet::difference`
- **BC BREAK:** `Duration::compare` no longer accept direct `DateTimeInterface` instances

### Deprecated

- None

### Removed

- **BC BREAK:** `DivisionResult::asTuple` is removed use `ArrayAccess` for direct destructuring
- **BC BREAK:** `InputNormalizer::duration` no longer accepts `DateTimeInterface` implementing class

## [0.2.0 - botan](https://github.com/bakame-php/tokei/compare/0.1.0...0.2.0) - 2026-07-17

### Added

- `Event`, `Task`, `EventSet`, `TaskSet`, `Identifiers` to work with identified Temporal Values.
- `IntervalSet::each`
- `IntervalSet::transform`
- `IntervalSet::chronological`
- `IntervalSet::add`
- `IntervalSet::sub`
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
- `Duration::fullDay`
- `Duration::format`
- `Duration::add`
- `Duration::sub`
- `Duration::dividedInto`
- `Duration::isZero`
- `Duration::in`
- `Duration::modulo`
- `Time::fromDateTime`
- `Time::toDateTime`
- `Time::fromFormat`
- `Time::format`
- `Time::sinceMidnight`
- `Time::offset`
- `Time::add`
- `Time::sub`
- `Time::utc`
- `Time::roundTo`
- `TimeFormatter`to improve time string localization using `ext-intl`
- `SnapMode` to unify rounding
- `SearchMode` to unify search type (linear or circular)
- `LocaleVerbosity` to allow fine-grained locale string representation using by `TimeFormatter`

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
- **BC BREAK:** `Duration::increment` is removed use `Duration::add` instead
- **BC BREAK:** `Duration::isEmpty` is removed use `Duration::isZero` instead
- **BC BREAK:** `Duration::total` is removed and replaced by `Duration::in`
- **BC BREAK:** all `Duration` public properties are removed except for `microseconds` and `sign`
- **BC BREAK:** `Interval::fromIso8601` is removed and replaced by `Interval::fromFormat` with the `IntervalFormat::Iso8601StartDuration` argument
- **BC BREAK:** `Interval::compareDurationTo` is removed use `Duration::compare` instead
- **BC BREAK:** `SubSecondDisplay` is removed with no remplacement use rounding with the `Rounding::Floor` mode
- **BC BREAK:** `truncateTo` is removed use `roundTo` instead with the new `Rounding:Floor` mode
- **BC BREAK:** `Time::fromDate` is removed use `Time::fromDateTime` instead
- **BC BREAK:** `Time::fromUnitOfDay` is removed use `Time::sinceMidnight` instead
- **BC BREAK:** `Time::toUnitOfDay` is removed use `Time::in` instead
- **BC BREAK:** `Time::toString` is removed use `Time::format` instead
- **BC BREAK:** `IntervalSet::sorted` argument was a string or PHP8.6 `SorDirection` enum is changed to only supports PHP8.6 `SorDirection` enum.
- **BC BREAK:** `IntervalSet::allFormatted` is removed use `IntervalSet::formatAll` instead

## [0.1.0 - asagao](https://github.com/bakame-php/tokei/releases/tag/0.1.0) - 2026-05-27

**Initial release!**
