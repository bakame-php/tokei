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
            match (true) {
                $time instanceof DateTimeInterface => Time::fromDateTime($time),
                $time instanceof NativeEvent => Event::fromNative($time)->at,
                $time instanceof Event => $time->at,
                default => $time,
            },
            match (true) {
                $identifier instanceof HasIdentifiers => $identifier->identifiers,
                $identifier instanceof Identifiers => $identifier,
                default => new Identifiers($identifier),
            }
        );
    }

    /**
     * @see TimeFormat::decode()
     *
     * @throws InvalidTime|TemporalException
     */
    public static function fromFormat(string $value, TimeFormat $format = TimeFormat::Iso8601): self
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
    public function format(TimeFormat $format = TimeFormat::Iso8601): string
    {
        return $format->encode($this->at).';'.$this->identifiers->toCommaSeparated();
    }

    public function equals(Event $other): bool
    {
        return $this->at->equals($other)
            && $this->identifiers->equals($other);
    }

    public function occursOn(Time|NativeEvent|DateTimeInterface|Event $at): self
    {
        $at = match (true) {
            $at instanceof DateTimeInterface => Time::fromDateTime($at),
            $at instanceof NativeEvent => Event::fromNative($at)->at,
            $at instanceof Event => $at->at,
            default => $at,
        };

        return $at->equals($this->at) ? $this : new self($at, $this->identifiers);
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
