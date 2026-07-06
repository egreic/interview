<?php

declare(strict_types=1);

namespace App\Module\ProfileEnrichment\Adapter;

/**
 * Contract of the team's existing LinkedIn provider (§0.3 hypothesis):
 * two methods, search + fetch. Every call is metered.
 */
interface LinkedInClientInterface
{
    /** @param array<string, string> $hints e.g. school name, graduation year
     *  @return list<array<string, mixed>> candidate profiles */
    public function search(string $lastName, string $firstName, array $hints = []): array;

    /** @return array<string, mixed> full profile payload */
    public function fetch(string $profileId): array;
}
