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
     * @param Identifiers|non-empty-string $identifier
     *
     * @throws TemporalException
     */
    public static function for(Interval $period, Identifiers|string $identifier = new Identifiers()): self
    {
        return new self($period, !$identifier instanceof Identifiers ? new Identifiers($identifier) : $identifier);
    }

    /**
     * @return non-empty-string
     */
    public function jsonSerialize(): string
    {
        return $this->format();
    }

    /**
     * @see IntervalFormat::decode()
     *
     * @throws InvalidInterval|TemporalException
     */
    public static function fromFormat(string $value, IntervalFormat $format = IntervalFormat::Iso8601StartDuration, ?Unit $unit = null): self
    {
        [$period, $identifiers] = explode(';', $value, 2) + [1 => ''];

        return new self(Interval::fromFormat($period, $format, $unit), Identifiers::fromFormat($identifiers));
    }

    /**
     * @see IntervalFormat::encode()
     *
     * @return non-empty-string
     */
    public function format(IntervalFormat $format = IntervalFormat::Iso8601StartDuration, ?Unit $unit = null): string
    {
        return $format->encode($this->period, $unit).';'.$this->identifiers->formatted();
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
     * @param Identifiers|non-empty-string $identifier
     *
     * @throws TemporalException
     */
    public function named(Identifiers|string $identifier): static
    {
        $identifier = $identifier instanceof Identifiers ? $identifier : new Identifiers($identifier);

        return $identifier->equals($this->identifiers) ? $this : new self($this->period, $identifier);
    }

    public function toEvent(Time $at): Event
    {
        return Event::at($at, $this->identifiers);
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
