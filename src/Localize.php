<?php

declare(strict_types=1);

namespace Bakame\Tokei;

use Bakame\Tokei\Internal\DurationParts;
use Bakame\Tokei\Internal\InputNormalizer;
use DateInterval;
use DateTimeInterface;
use DateTimeZone;
use IntlDateFormatter;
use IntlListFormatter;
use MessageFormatter;
use Throwable;
use Time\Duration as TimeDuration;
use ValueError;

use function array_key_first;
use function class_exists;
use function count;
use function str_replace;

final readonly class Localize
{
    /**
     * @return array{
     *     weeks: non-empty-string,
     *     days: non-empty-string,
     *     hours: non-empty-string,
     *     minutes: non-empty-string,
     *     seconds: non-empty-string,
     *     milliseconds: non-empty-string,
     *     microseconds: non-empty-string,
     *     nanoseconds: non-empty-string,
     * }
     */
    private const array DURATION_ICU_UNIT_MAP = [
        'weeks' => '{value, number, ::unit/week %s}',
        'days' => '{value, number, ::unit/day %s}',
        'hours' => '{value, number, ::unit/hour %s}',
        'minutes' => '{value, number, ::unit/minute %s}',
        'seconds' => '{value, number, ::unit/second %s}',
        'milliseconds' => '{value, number, ::unit/millisecond %s}',
        'microseconds' => '{value, number, ::unit/microsecond %s}',
        'nanoseconds' => '{value, number, ::unit/nanosecond %s}',
    ];

    private const int MAXIMUM_LOCALES_CACHED = 10;
    private const int MAXIMUM_FORMATTERS_CACHED = 50;

    private function __construct()
    {
    }

    /**
     * Locale aware formatting of a time.
     *
     * @param non-empty-string $locale
     * @param DateTimeZone|DateTimeInterface|non-empty-string $timezone
     *
     * @throws TokeiException
     *
     * @return non-empty-string
     */
    public static function time(
        Time|Event|DateTimeInterface $time,
        string $locale,
        DateTimeZone|DateTimeInterface|string $timezone = 'UTC',
        TimeVerbosity $verbosity = TimeVerbosity::Medium,
    ): string {
        static $isSupported = null;

        $isSupported ??= class_exists(IntlDateFormatter::class);
        $isSupported || throw new TimeException('Support for time locale formatting requires the `intl` extension or `symfony/polyfill-intl-icu`.');

        $timezone = InputNormalizer::timezone($timezone);
        $dateTime = $time instanceof DateTimeInterface ? $time : InputNormalizer::time($time)->today($timezone);
        $dateTimezone = $dateTime->getTimezone();
        $formatter = self::getIntlDateFormatter($locale, $dateTimezone, $verbosity);
        $formatted = $formatter->format($dateTime);

        return (false !== $formatted && '' !== $formatted)
            ? $formatted
            : throw new TimeException('Unable to format the time for locale "'.$locale.'" and timezone: "'.$dateTimezone->getName().'"; '.$formatter->getErrorMessage());
    }

    /**
     * Locale aware formatting of the duration absolute form.
     *
     * @param non-empty-string $locale
     *
     * @throws TokeiException
     *
     * @return non-empty-string
     */
    public static function duration(
        Duration|DateInterval|Interval|Task|TimeDuration $duration,
        string $locale,
        UnitWidth $unitWidth = UnitWidth::Wide,
        ListWidth $listWidth = ListWidth::Wide,
    ): string {
        static $isSupported = null;

        $isSupported ??= class_exists(IntlListFormatter::class);
        $isSupported || throw new TimeException('Support for duration locale formatting requires the `intl` extension or `symfony/polyfill-intl-icu` version 1.34 or above.');

        $parts = [];
        foreach (new DurationParts($duration)->decompose() as $unit => $value) {
            if (0 !== $value) {
                $parts[] = self::formatUnit($value, $unit, $locale, $unitWidth);
            }
        }

        $nbParts = count($parts);
        if (0 === $nbParts) {
            return self::formatUnit(0, 'seconds', $locale, $unitWidth);
        }

        if (1 === $nbParts) {
            return $parts[0];
        }

        $listFormatter = self::getIntlListFormatter($locale, $listWidth);
        $result = $listFormatter->format($parts);

        return '' !== $result ? $result : throw new TokeiException('Unable to format the duration; '.$listFormatter->getErrorMessage());
    }

    /**
     * @param non-empty-string $unit
     * @param non-empty-string $locale
     *
     * @throws TokeiException
     *
     * @return non-empty-string
     */
    private static function formatUnit(int $value, string $unit, string $locale, UnitWidth $unitWidth): string
    {
        $formatter = self::getMessageFormatter($unit, $locale, $unitWidth);
        $result = $formatter->format(['value' => $value]);

        return (false !== $result && '' !== $result) ? $result : throw new TokeiException('Unable to format duration '.$value.$unit.' for '.$locale.'; '.$formatter->getErrorMessage());
    }

    /**
     * @param non-empty-string $unit
     * @param non-empty-string $locale
     *
     * @throws TokeiException
     */
    private static function getMessageFormatter(string $unit, string $locale, UnitWidth $unitWidth): MessageFormatter
    {
        /** @var array<non-empty-string, array<non-empty-string, MessageFormatter>> $inMemoryCache */
        static $inMemoryCache = [];

        $pattern = str_replace('%s', match ($unitWidth) {
            UnitWidth::Wide => 'unit-width-full-name',
            UnitWidth::Narrow => 'unit-width-narrow',
            UnitWidth::Short => 'unit-width-short',
        }, self::DURATION_ICU_UNIT_MAP[$unit] ?? throw new TokeiException('Unknown duration unit "'.$unit.'".'));

        if (isset($inMemoryCache[$locale][$pattern])) {
            return $inMemoryCache[$locale][$pattern];
        }

        if (self::MAXIMUM_LOCALES_CACHED <= count($inMemoryCache)) {
            unset($inMemoryCache[array_key_first($inMemoryCache)]);
        }

        $inMemoryCache[$locale] ??= [];

        try {
            return $inMemoryCache[$locale][$pattern] ??= new MessageFormatter($locale, $pattern);
        } catch (Throwable $exception) {
            throw new TokeiException('Unable to format unit "'.$unit.'" for '.$locale.' locale.', previous: $exception);
        }
    }

    /**
     * @param non-empty-string $locale
     */
    private static function getIntlListFormatter(string $locale, ListWidth $listWidth): IntlListFormatter
    {
        /** @var array<non-empty-string, IntlListFormatter> $inMemoryCache */
        static $inMemoryCache = [];
        $cacheKey = $locale.'_'.$listWidth->name;
        if (isset($inMemoryCache[$cacheKey])) {
            return $inMemoryCache[$cacheKey];
        }

        if (self::MAXIMUM_LOCALES_CACHED <= count($inMemoryCache)) {
            unset($inMemoryCache[array_key_first($inMemoryCache)]);
        }

        try {
            return $inMemoryCache[$cacheKey] = new IntlListFormatter(
                $locale,
                IntlListFormatter::TYPE_AND,
                match ($listWidth) {
                    ListWidth::Narrow => IntlListFormatter::WIDTH_NARROW,
                    ListWidth::Short => IntlListFormatter::WIDTH_SHORT,
                    ListWidth::Wide => IntlListFormatter::WIDTH_WIDE,
                }
            );
        } catch (Throwable $exception) {
            throw new ValueError('Unable to instantiate '.self::class.' for locale "'.$locale.'".', previous: $exception);
        }
    }

    /**
     * @param non-empty-string $locale
     */
    private static function getIntlDateFormatter(
        string $locale,
        DateTimeZone $timezone,
        TimeVerbosity $verbosity,
    ): IntlDateFormatter {
        /** @var array<non-empty-string, IntlDateFormatter> $inMemoryCache */
        static $inMemoryCache = [];

        $key = $locale.':'.$verbosity->name.':'.$timezone->getName();
        if (isset($inMemoryCache[$key])) {
            return $inMemoryCache[$key];
        }

        if (self::MAXIMUM_FORMATTERS_CACHED <= count($inMemoryCache)) {
            unset($inMemoryCache[array_key_first($inMemoryCache)]);
        }

        try {
            return $inMemoryCache[$key] = new IntlDateFormatter(
                locale: $locale,
                dateType: IntlDateFormatter::NONE,
                timeType: match ($verbosity) {
                    TimeVerbosity::Full => IntlDateFormatter::FULL,
                    TimeVerbosity::Long => IntlDateFormatter::LONG,
                    TimeVerbosity::Medium => IntlDateFormatter::MEDIUM,
                    TimeVerbosity::Short => IntlDateFormatter::SHORT,
                },
                timezone: $timezone,
            );
        } catch (Throwable $exception) {
            throw new ValueError(
                'Unable to instantiate '.self::class.' for locale "'.$locale.'".',
                previous: $exception,
            );
        }
    }
}
