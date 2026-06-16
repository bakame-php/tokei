<?php

declare(strict_types=1);

namespace Bakame\Tokei;

use JsonSerializable;

use function explode;

final readonly class Task implements HasIdentifiers, JsonSerializable
{
    private function __construct(
        public Interval $period,
        public Identifiers $identifiers,
    ) {
    }

    /**
     * @param Identifiers|HasIdentifiers|non-empty-string $identifier
     *
     * @throws TemporalException
     */
    public static function for(Interval $period, Identifiers|HasIdentifiers|string $identifier = new Identifiers()): self
    {
        return new self($period, match (true) {
            $identifier instanceof HasIdentifiers => $identifier->identifiers,
            $identifier instanceof Identifiers => $identifier,
            default => new Identifiers($identifier),
        });
    }

    public static function fromEvent(Event $event, Duration $duration, Bound $from): self
    {
        return self::for(
            Bound::Start === $from
                ? Interval::since($event->at, $duration)
                : Interval::until($event->at, $duration),
            $event->identifiers
        );
    }

    /**
     * @see IntervalFormat::decode()
     *
     * @throws InvalidInterval|TemporalException
     */
    public static function fromFormat(string $value, IntervalFormat $format = IntervalFormat::Iso8601StartDuration, ?Unit $unit = null): self
    {
        [$period, $identifiers] = explode(';', $value, 2) + [1 => ''];

        return new self(Interval::fromFormat($period, $format, $unit), Identifiers::fromCommaSeparated($identifiers));
    }

    /**
     * @return non-empty-string
     */
    public function jsonSerialize(): string
    {
        return $this->format();
    }

    /**
     * @see IntervalFormat::encode()
     *
     * @return non-empty-string
     */
    public function format(IntervalFormat $format = IntervalFormat::Iso8601StartDuration, ?Unit $unit = null): string
    {
        return $format->encode($this->period, $unit).';'.$this->identifiers->asCommaSeparated();
    }

    public function equals(Task $other): bool
    {
        return $this->period->equals($other)
            && $this->identifiers->equals($other);
    }

    public function during(Task|Interval $period): self
    {
        $period = $period instanceof Task ? $period->period : $period;

        return $period->equals($this->period) ? $this : new self($period, $this->identifiers);
    }

    /**
     * @param Identifiers|HasIdentifiers|non-empty-string $identifier
     *
     * @throws TemporalException
     */
    public function named(Identifiers|HasIdentifiers|string $identifier): static
    {
        $identifier = match (true) {
            $identifier instanceof HasIdentifiers => $identifier->identifiers,
            $identifier instanceof Identifiers => $identifier,
            default => new Identifiers($identifier),
        };

        return $identifier->equals($this->identifiers) ? $this : new self($this->period, $identifier);
    }

    /**
     * @return array{0: array{period: Interval, identifiers: Identifiers}, 1: array{}}
     */
    public function __serialize(): array
    {
        return [['period' => $this->period, 'identifiers' => $this->identifiers], []];
    }

    /**
     * @param array{0: array{period: Interval, identifiers: Identifiers}, 1: array{}} $data
     */
    public function __unserialize(array $data): void
    {
        [$properties] = $data;
        $this->period = $properties['period'];
        $this->identifiers = $properties['identifiers'];
    }
}
