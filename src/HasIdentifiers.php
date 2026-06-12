<?php

declare(strict_types=1);

namespace Bakame\Tokei;

interface HasIdentifiers
{
    public Identifiers $identifiers { get; }

    /**
     * @param Identifiers|non-empty-string $identifier
     *
     * @throws TemporalException
     */
    public function named(Identifiers|string $identifier): static;
}
