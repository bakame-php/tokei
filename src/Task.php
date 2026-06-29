<?php

declare(strict_types=1);

namespace Bakame\Tokei;

use DateInterval;
use DateTimeInterface;
use JsonSerializable;

use function explode;

final readonly class Task implements HasIdentifiers, JsonSerializable
{
    private function __construct(
        public Interval $interval,
        public Identifiers $identifiers,
    ) {
    }

    /**
     * @return array{0: array{interval: Interval, identifiers: Identifiers}, 1: array{}}
     */
    public function __serialize(): array
    {
        return [['interval' => $this->interval, 'identifiers' => $this->identifiers], []];
    }

    /**
     * @param array{0: array{interval: Interval, identifiers: Identifiers}, 1: array{}} $data
     */
    public function __unserialize(array $data): void
    {
        [$properties] = $data;
        $this->interval = $properties['interval'];
        $this->identifiers = $properties['identifiers'];
    }

    /**
     * @param Identifiers|HasIdentifiers|non-empty-string $identifier
     *
     * @throws TemporalException
     */
    public static function for(Interval|NativeInterval|Task|NativeTask $interval, Identifiers|HasIdentifiers|string $identifier = new Identifiers()): self
    {
        return new self(
            InputNormalizer::interval($interval),
            InputNormalizer::identifiers($identifier),
        );
    }

    public static function fromEvent(Event $event, Duration|DateInterval|Interval|Task|NativeInterval|NativeTask $duration, Bound $from): self
    {
        return self::for(
            Bound::Start === $from
                ? Interval::since($event->at, $duration)
                : Interval::until($event->at, $duration),
            $event->identifiers
        );
    }

    /**
     * @throws InvalidInterval|TemporalException
     */
    public static function fromFormat(string $value, IntervalFormat $format = IntervalFormat::Iso8601StartDuration, ?Unit $unit = null): self
    {
        [$interval, $identifiers] = explode(';', $value, 2) + [1 => ''];

        return new self(Interval::fromFormat($interval, $format, $unit), Identifiers::fromCommaSeparated($identifiers));
    }

    /**
     * @return non-empty-string
     */
    public function jsonSerialize(): string
    {
        return $this->format();
    }

    /**
     * @return non-empty-string
     */
    public function format(IntervalFormat $format = IntervalFormat::Iso8601StartDuration, ?Unit $unit = null): string
    {
        return $this->interval->format($format, $unit).';'.$this->identifiers->toCommaSeparated();
    }

    public function equals(Task $other): bool
    {
        return $this->interval->equals($other)
            && $this->identifiers->equals($other);
    }

    public function during(Task|Interval|NativeInterval|NativeTask $interval): self
    {
        $interval = InputNormalizer::interval($interval);

        return $this->interval->equals($interval) ? $this : self::for($interval, $this->identifiers);
    }

    /**
     * @param Identifiers|HasIdentifiers|non-empty-string $identifier
     *
     * @throws TemporalException
     */
    public function named(Identifiers|HasIdentifiers|string $identifier): static
    {
        $identifier = InputNormalizer::identifiers($identifier);

        return $identifier->equals($this->identifiers) ? $this : new self($this->interval, $identifier);
    }

    public function toNative(DateTimeInterface $reference): NativeTask
    {
        return new NativeTask($this->interval->toNative($reference), $this->identifiers);
    }

    public static function fromNative(NativeTask $task): self
    {
        return new self(Interval::fromNative($task->interval), $task->identifiers);
    }
}
