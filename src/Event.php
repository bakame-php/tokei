<?php

declare(strict_types=1);

namespace Bakame\Tokei;

use DateTimeInterface;
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
     * @param Identifiers|HasIdentifiers|non-empty-string $identifier
     *
     * @throws TemporalException
     */
    public static function at(Time|NativeEvent|DateTimeInterface|Event $time, Identifiers|HasIdentifiers|string $identifier = new Identifiers()): self
    {
        return new self(
            InputNormalizer::time($time),
            InputNormalizer::identifiers($identifier),
        );
    }

    /**
     * @throws InvalidTime|TemporalException
     */
    public static function fromFormat(string $value, TimeFormat $format = TimeFormat::Iso8601Extended): self
    {
        [$time, $identifiers] = explode(';', $value, 2) + [1 => ''];

        return new self(Time::fromFormat($time, $format), Identifiers::fromCommaSeparated($identifiers));
    }

    public static function fromTask(Task $task, Bound $anchor): self
    {
        return new self(Bound::Start === $anchor ? $task->interval->start : $task->interval->end, $task->identifiers);
    }

    public static function fromNative(NativeEvent $event): self
    {
        return self::at(Time::fromDateTime($event->at), $event->identifiers);
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
    public function format(TimeFormat $format = TimeFormat::Iso8601Extended): string
    {
        return $this->at->format($format).';'.$this->identifiers->toCommaSeparated();
    }

    public function equals(Event $other): bool
    {
        return $this->at->equals($other)
            && $this->identifiers->equals($other);
    }

    public function occursOn(Time|NativeEvent|DateTimeInterface|Event $at): self
    {
        $at = InputNormalizer::time($at);

        return $at->equals($this->at) ? $this : new self($at, $this->identifiers);
    }

    /**
     * @param Identifiers|HasIdentifiers|non-empty-string $identifier
     *
     * @throws TemporalException
     */
    public function named(Identifiers|HasIdentifiers|string $identifier): static
    {
        $identifier = InputNormalizer::identifiers($identifier);

        return $identifier->equals($this->identifiers) ? $this : new self($this->at, $identifier);
    }

    public function toNative(DateTimeInterface $reference): NativeEvent
    {
        return new NativeEvent($this->at->applyTo($reference), $this->identifiers);
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
