<?php

declare(strict_types=1);

namespace Bakame\Tokei;

use JsonSerializable;

use function explode;

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
     * @see TimeFormat::decode()
     *
     * @throws InvalidTime|TemporalException
     */
    public static function fromFormat(string $value, TimeFormat $format = TimeFormat::Iso8601): self
    {
        [$time, $identifiers] = explode(';', $value, 2) + [1 => ''];

        return new self(Time::fromFormat($time, $format), Identifiers::fromFormat($identifiers));
    }

    /**
     * @see IntervalFormat::encode()
     *
     * @return non-empty-string
     */
    public function format(TimeFormat $format = TimeFormat::Iso8601): string
    {
        return $format->encode($this->at).';'.$this->identifiers->formatted();
    }

    /**
     * @return non-empty-string
     */
    public function jsonSerialize(): string
    {
        return $this->format();
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
     * @param Identifiers|non-empty-string $identifier
     *
     * @throws TemporalException
     */
    public function named(Identifiers|string $identifier): static
    {
        $identifier = $identifier instanceof Identifiers ? $identifier : new Identifiers($identifier);

        return $identifier->equals($this->identifiers) ? $this : new self($this->at, $identifier);
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
