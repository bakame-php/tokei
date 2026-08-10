<?php

declare(strict_types=1);

namespace Tests;

use Bakame\Tokei\TimeFormatter;
use Bakame\Tokei\LocaleVerbosity;
use Bakame\Tokei\Time;
use Bakame\Tokei\TimeException;
use DateTimeZone;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ValueError;

#[CoversClass(TimeFormatter::class)]
#[CoversClass(TimeException::class)]
final class TimeFormatterTest extends TestCase
{
    public function testCanBeInstantiatedWithDefaults(): void
    {
        $formatter = new TimeFormatter('en_US');

        self::assertSame('en_US', $formatter->locale);
        self::assertSame('UTC', $formatter->timezone->getName());
        self::assertSame(LocaleVerbosity::Medium, $formatter->verbosity);
    }

    public function testCanBeInstantiatedWithTimezoneString(): void
    {
        $formatter = new TimeFormatter(locale: 'en_US', timezone: 'Europe/Brussels');

        self::assertSame('Europe/Brussels', $formatter->timezone->getName());
    }

    public function testCanBeInstantiatedWithTimezoneObject(): void
    {
        $timezone = new DateTimeZone('Europe/Brussels');
        $formatter = new TimeFormatter(locale: 'en_US', timezone: $timezone);

        self::assertSame('Europe/Brussels', $formatter->timezone->getName());
    }

    public function testRejectsInvalidTimezone(): void
    {
        $this->expectException(TimeException::class);

        new TimeFormatter(locale: 'en_US', timezone: 'Mars/Phobos');
    }

    public function testRejectsInvalidLocale(): void
    {
        $this->expectException(ValueError::class);

        new TimeFormatter('this-is-not-a-locale');
    }

    public function testFormatsTime(): void
    {
        $formatter = new TimeFormatter('en_US');
        $formatted = $formatter->format(Time::at(hour: 14, minute: 30));

        self::assertStringContainsString('PM', $formatted);
    }

    public function testFormatsUsingFormatterTimezone(): void
    {
        $formatter = new TimeFormatter(locale: 'en_US', timezone: 'Europe/Brussels');
        $result = $formatter->format(Time::at(hour: 10));

        self::assertStringContainsString('AM', $result);
    }

    public function testCanOverrideTimezone(): void
    {
        $formatter = new TimeFormatter(locale: 'en_US', timezone: 'UTC', verbosity: LocaleVerbosity::Full);
        $noon = Time::at(hour: 14, minute: 30, second: 13);

        self::assertNotSame(
            $formatter->format($noon),
            $formatter->format($noon, 'Asia/Tokyo'),
        );
    }

    public function testTimezoneOverrideDoesNotMutateFormatter(): void
    {
        $formatter = new TimeFormatter(locale: 'tr_CY', timezone: 'UTC', verbosity: LocaleVerbosity::Full);
        $noon = Time::noon();
        $first = $formatter->format($noon);
        $changedTimezone = $formatter->format($noon, 'Asia/Tokyo');
        $second = $formatter->format($noon);

        self::assertSame($first, $second);
        self::assertNotSame($first, $changedTimezone);
    }

    public function test_with_preserves_original_instance(): void
    {
        $original = Time::at(23, 54, 23);
        $updated = $original->with(hour: 8);

        $formatterShort = new TimeFormatter(locale: 'tr_CY', timezone: 'UTC', verbosity: LocaleVerbosity::Short);
        $formatterLong = new TimeFormatter(locale: 'tr_CY', timezone: 'UTC', verbosity: LocaleVerbosity::Long);

        self::assertNotSame($formatterShort->format($updated), $formatterLong->format($updated));
    }
}
