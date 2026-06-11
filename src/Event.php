<?php

declare(strict_types=1);

namespace Bakame\Tokei;

use JsonSerializable;

/**
 * @phpstan-import-type InputIdentifiers from HasIdentifiers
 */
final class Event implements HasIdentifiers, JsonSerializable
{
    private function __construct(
        public Time $at,
        public Identifiers $identifiers,
    ) {
    }

    /**
     * @param Identifiers|non-empty-string $identifier
     *
     * @throws TemporalException
     */
    public static function at(Time $time, Identifiers|string $identifier = new Identifiers()): self
    {
        return new self($time, !$identifier instanceof Identifiers ? new Identifiers($identifier) : $identifier);
    }

    /**
     * @return array{at: Time, identifiers: Identifiers}
     */
    public function jsonSerialize(): mixed
    {
        return [
            'at' => $this->at,
            'identifiers' => $this->identifiers,
       ];
    }

    public function identifiers(): Identifiers
    {
        return $this->identifiers;
    }

    public function equals(Event $other): bool
    {
        return $this->at->equals($other)
            && $this->identifiers->equals($other);
    }

    public function occursOn(Event|Time $at): self
    {
        $at = $at instanceof self ? $at->at : $at;

        return $at->equals($this->at) ? $this : new self($at, $this->identifiers);
    }

    /**
     * @param InputIdentifiers $identifiers
     *
     * @throws TemporalException
     */
    public function named(Identifiers|HasIdentifiers|iterable|string $identifiers): static
    {
        $identifiers = $identifiers instanceof Identifiers ? $identifiers : new Identifiers($identifiers);

        return $identifiers->equals($this->identifiers) ? $this : new self($this->at, $identifiers);
    }

    public function toTask(Interval $period): Task
    {
        return Task::for($period, $this->identifiers);
    }

    /**
     * @return array{0: array{at: Time, identifiers: Identifiers}, 1: array{}}
     */
    public function __serialize(): array
    {
        return [['at' => $this->at, 'identifiers' => $this->identifiers], []];
    }

    /**
     * @param array{0: array{at: Time, identifiers: Identifiers}, 1: array{}} $data
     */
    public function __unserialize(array $data): void
    {
        [$properties] = $data;
        $this->at = $properties['at'];
        $this->identifiers = $properties['identifiers'];
    }
}
