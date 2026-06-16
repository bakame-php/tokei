<?php

declare(strict_types=1);

namespace Bakame\Tokei;

interface HasIdentifiers
{
    public Identifiers $identifiers { get; }

    /**
     * @param Identifiers|HasIdentifiers|non-empty-string $identifier
     *
     * @throws TemporalException
     */
    public function named(Identifiers|HasIdentifiers|string $identifier): static;
}
