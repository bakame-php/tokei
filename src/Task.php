<?php

declare(strict_types=1);

namespace Bakame\Tokei;

use JsonSerializable;

/**
 * @phpstan-import-type InputIdentifiers from HasIdentifiers
 */
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

    public function identifiers(): Identifiers
    {
        return $this->identifiers;
    }

    /**
     * @return array{period: Interval, identifiers: Identifiers}
     */
    public function jsonSerialize(): array
    {
        return [
            'period' => $this->period,
            'identifiers' => $this->identifiers,
        ];
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
     * @param InputIdentifiers $identifiers
     *
     * @throws TemporalException
     */
    public function named(Identifiers|HasIdentifiers|iterable|string $identifiers): static
    {
        $identifiers = $identifiers instanceof Identifiers ? $identifiers : new Identifiers($identifiers);

        return $identifiers->equals($this->identifiers) ? $this : new self($this->period, $identifiers);
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
