<?php

declare(strict_types=1);

namespace Bakame\Tokei;

use DateTimeInterface;
use DateTimeZone;
use IntlDateFormatter;
use Throwable;
use ValueError;

use function array_key_first;
use function class_exists;
use function count;

final readonly class LocaleTimeFormatter
{
    private const int MAXIMUM_FORMATTER_CACHED = 50;

    private IntlDateFormatter $formatter;
    public DateTimeZone $timezone;

    /**
     * @param non-empty-string $locale
     * @param DateTimeInterface|DateTimeZone|non-empty-string $timezone
     *
     * @throws TimeException
     */
    public function __construct(
        public string $locale,
        DateTimeInterface|DateTimeZone|string $timezone = 'UTC',
        public LocaleVerbosity $verbosity = LocaleVerbosity::Medium,
    ) {
        self::supportsIntl();
        $timezone = InputNormalizer::timezone($timezone);

        try {
            $formatter = new IntlDateFormatter(
                locale: $locale,
                dateType: IntlDateFormatter::NONE,
                timeType: match ($verbosity) {
                    LocaleVerbosity::Full => IntlDateFormatter::FULL,
                    LocaleVerbosity::Long => IntlDateFormatter::LONG,
                    LocaleVerbosity::Medium => IntlDateFormatter::MEDIUM,
                    LocaleVerbosity::Short => IntlDateFormatter::SHORT,
                },
                timezone: $timezone,
            );
        } catch (Throwable $exception) {
            throw new ValueError('Unable to instantiate '.self::class.'; verify the locale.', previous: $exception);
        }

        $this->timezone = $timezone;
        $this->formatter = $formatter;
    }

    /**
     * Formats a Time or a DateTime instance according to the formatter's locale.
     *
     * The timezone used for formatting is determined as follows:
     * - If a DateTimeInterface is provided its timezone information is used
     *
     * otherwise:
     * - If $timezone is null, the formatter's default timezone is used.
     * - If a timezone is provided, it is used instead.
     * - If the timezone differs from the formatter's timezone, a dedicated formatter is used for that timezone.
     *
     * @param DateTimeZone|DateTimeInterface|non-empty-string|null $timezone
     *
     * @throws TimeException
     */
    public function format(DateTimeInterface|Time $time, DateTimeZone|DateTimeInterface|string|null $timezone = null): string
    {
        $dateTime = $time instanceof Time
            ? $time->toDateTime($timezone ?? $this->timezone)
            : $time;

        $timezone = $dateTime->getTimezone();
        $formatted = $this->formatterFor($timezone)->format($dateTime);

        return false !== $formatted
            ? $formatted
            : throw new TimeException('Unable to format the time for locale "'.$this->locale.'" and timezone: "'.$timezone->getName().'".');
    }

    private function formatterFor(DateTimeZone $timezone): IntlDateFormatter
    {
        /** @var array<non-empty-string, IntlDateFormatter> $inMemoryCache */
        static $inMemoryCache = [];
        $name = $timezone->getName();
        $key = $this->locale.':'.$this->verbosity->name.':'.$name;
        if (isset($inMemoryCache[$key])) {
            return $inMemoryCache[$key];
        }

        if ($name === $this->timezone->getName()) {
            return $inMemoryCache[$key] ??= $this->formatter;
        }

        if (self::MAXIMUM_FORMATTER_CACHED <= count($inMemoryCache)) {
            unset($inMemoryCache[array_key_first($inMemoryCache)]);
        }

        $formatter = clone $this->formatter;
        $formatter->setTimeZone($timezone);

        return $inMemoryCache[$key] = $formatter;
    }

    private static function supportsIntl(): void
    {
        static $isSupported = null;
        $isSupported = $isSupported ?? class_exists(IntlDateFormatter::class);
        $isSupported || throw new TimeException('Support for time locale formatting requires the `intl` extension for best performance or run "composer require symfony/polyfill-intl-icu" to install a polyfill.');
    }
}
