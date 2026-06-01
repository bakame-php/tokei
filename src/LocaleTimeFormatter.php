<?php

declare(strict_types=1);

namespace Bakame\Tokei;

use DateTimeImmutable;
use DateTimeZone;
use IntlDateFormatter;
use Throwable;

use function class_exists;

use const PHP_VERSION_ID;

final readonly class LocaleTimeFormatter
{
    private IntlDateFormatter $formatter;
    public DateTimeZone $timezone;

    /**
     * @param non-empty-string $locale
     * @param DateTimeZone|non-empty-string $timezone
     *
     * @throws TimeException
     */
    public function __construct(
        public string $locale,
        DateTimeZone|string $timezone = 'UTC',
        public LocaleVerbosity $verbosity = LocaleVerbosity::Medium,
    ) {
        $this->timezone = self::filterTimezone($timezone);
        $this->formatter = self::createFormatter($this->locale, $this->verbosity, $this->timezone);
    }

    private static function createFormatter(string $locale, LocaleVerbosity $verbosity, DateTimeZone $timezone): IntlDateFormatter
    {
        self::supportsIntl();

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

            // For PHP83 only we are required to test the instantiated instance to
            // validate that it is in a valid state. An error is only thrown with
            // the following message "Found unconstructed IntlDateFormatter"
            // when format is called,
            if (PHP_VERSION_ID < 80400) {
                $formatter->format(new DateTimeImmutable());
            }
        } catch (Throwable $exception) {
            throw new TimeException('Unable to instantiate '.self::class.'; verify the locale.', previous: $exception);
        }

        return $formatter;
    }

    /**
     * Formats a Time instance according to the formatter's locale.
     *
     * The timezone used for formatting is determined as follows:
     * - If $timezone is null, the formatter's default timezone is used.
     * - If a timezone is provided, it is used instead.
     * - If the timezone differs from the formatter's timezone, a dedicated
     *   formatter is used for that timezone.
     *
     * @param DateTimeZone|non-empty-string|null $timezone
     *
     * @throws TimeException
     */
    public function format(Time $time, DateTimeZone|string|null $timezone = null): string
    {
        $timezone = null !== $timezone ? self::filterTimezone($timezone) : $this->timezone;
        $datetime = $time->toDateTime($timezone);
        $formatted = $this->timezone->getName() === $timezone->getName()
            ? $this->formatter->format($datetime)
            : $this->formatterFor($timezone)->format($datetime);

        return false !== $formatted
            ? $formatted
            : throw new TimeException('Unable to format time for locale "'.$this->locale.'" and timezone: "'.$timezone->getName().'".');
    }

    private function formatterFor(DateTimeZone $timezone): IntlDateFormatter
    {
        $formatter = clone $this->formatter;
        $formatter->setTimeZone($timezone);

        return $formatter;
    }

    /**
     * @param DateTimeZone|non-empty-string $timezone
     *
     * @throws TimeException
     */
    private static function filterTimezone(DateTimeZone|string $timezone): DateTimeZone
    {
        if ($timezone instanceof DateTimeZone) {
            return $timezone;
        }

        try {
            return new DateTimeZone($timezone);
        } catch (Throwable $exception) {
            throw TimeException::invalidTimezone(timezone: $timezone, previous: $exception);
        }
    }

    private static function supportsIntl(): void
    {
        static $isSupported = null;
        $isSupported = $isSupported ?? class_exists(IntlDateFormatter::class);
        $isSupported || throw new TimeException('Support for time locale formatting requires the `intl` extension for best performance or run "composer require symfony/polyfill-intl-icu" to install a polyfill.');
    }
}
