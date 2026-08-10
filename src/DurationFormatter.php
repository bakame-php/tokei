<?php

declare(strict_types=1);

namespace Bakame\Tokei;

use Bakame\Tokei\Internal\DurationParts;
use Bakame\Tokei\Internal\InputNormalizer;
use DateInterval;
use IntlException;
use IntlListFormatter;
use MessageFormatter;
use Throwable;
use Time\Duration as TimeDuration;
use ValueError;

use function class_exists;
use function count;
use function max;

final readonly class DurationFormatter
{
    private const DURATION_ICU_UNIT_MAP = [
        'weeks' => 'week',
        'days' => 'day',
        'hours' => 'hour',
        'minutes' => 'minute',
        'seconds' => 'second',
        'milliseconds' => 'millisecond',
        'microseconds' => 'microsecond',
        'nanoseconds' => 'nanosecond',
    ];

    private IntlListFormatter $listFormatter;

    public function __construct(
        public string $locale,
        public ListWidth $listWidth = ListWidth::Wide,
    ) {
        self::supportsIntlListFormatter();
        try {
            $this->listFormatter = new IntlListFormatter(
                $this->locale,
                IntlListFormatter::TYPE_AND,
                match ($this->listWidth) {
                    ListWidth::Narrow => IntlListFormatter::WIDTH_NARROW,
                    ListWidth::Short => IntlListFormatter::WIDTH_SHORT,
                    ListWidth::Wide => IntlListFormatter::WIDTH_WIDE,
                }
            );
        } catch (Throwable $exception) {
            throw new ValueError('Unable to instantiate '.self::class.' for locale "'.$this->locale.'".', previous: $exception);
        }
    }

    /**
     * Locale aware formatting of the duration absolute form.
     *
     * @throws TokeiException
     *
     * @return non-empty-string
     */
    public function format(Duration|DateInterval|Interval|Task|TimeDuration $duration): string
    {
        $parts = [];
        foreach (new DurationParts(InputNormalizer::duration($duration))->decompose() as $unit => $value) {
            if (0 !== $value) {
                $parts[] = $this->formatUnit($value, $unit);
            }
        }

        return $this->formatList($parts);
    }

    /**
     * @param non-empty-string $unit
     *
     * @throws TokeiException
     *
     * @return non-empty-string
     */
    private function formatUnit(int $value, string $unit): string
    {
        $formatter = $this->getMessageFormatter($unit);
        /** @var non-empty-string|false $result */
        $result = $formatter->format(['value' => $value]);
        if (false === $result) {
            throw new TokeiException('Unable to format duration '.$value.$unit.' for '.$this->locale.'; '.$formatter->getErrorMessage());
        }

        return $result;
    }

    /**
     * @param non-empty-string $unit
     *
     * @throws TokeiException
     */
    private function getMessageFormatter(string $unit): MessageFormatter
    {
        $icuUnit = self::DURATION_ICU_UNIT_MAP[$unit] ?? throw new TokeiException('Unknown unit "'.$unit.'"');

        try {
            return new MessageFormatter($this->locale, '{value, number, ::unit/'.$icuUnit.' unit-width-full-name}');
        } catch (IntlException $exception) {
            throw new TokeiException('Unable to format the duration for '.$this->locale, previous: $exception);
        }
    }

    /**
     * @param list<non-empty-string> $parts
     *
     * @throws TokeiException
     *
     * @return non-empty-string
     */
    private function formatList(array $parts): string
    {
        $nbParts = count($parts);

        /** @var non-empty-string|false $result */
        $result = match ($nbParts) { /* @phpstan-ignore-line */
            0 => $this->formatUnit(0, 'seconds'),
            1 => $parts[0],
            default => $this->listFormatter->format($parts),
        };

        if (false === $result) {
            throw new TokeiException('Unable to format the duration; '.$this->listFormatter->getErrorMessage());
        }

        return $result;
    }

    private static function supportsIntlListFormatter(): void
    {
        static $isSupported = null;
        $isSupported ??= class_exists(IntlListFormatter::class);
        $isSupported || throw new TimeException('Support for duration locale formatting requires the `intl` extension for best performance or run "composer require symfony/polyfill-intl-icu" to install a polyfill.');
    }
}
