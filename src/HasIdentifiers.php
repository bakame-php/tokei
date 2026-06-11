<?php

declare(strict_types=1);

namespace Bakame\Tokei;

/**
 * @phpstan-type InputIdentifiers Identifiers|HasIdentifiers|(iterable<non-empty-string>)|non-empty-string
 */
interface HasIdentifiers
{
    public function identifiers(): Identifiers;

    /**
     * @param InputIdentifiers $identifiers
     *
     * @throws TemporalException
     */
    public function named(Identifiers|HasIdentifiers|iterable|string $identifiers): static;
}
