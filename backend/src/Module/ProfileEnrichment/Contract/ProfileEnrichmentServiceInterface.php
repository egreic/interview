<?php

declare(strict_types=1);

namespace App\Module\ProfileEnrichment\Contract;

/**
 * Brick contract — other bricks talk to profiles only through this interface
 * (never through this module's ODM documents). Implemented at J3.
 */
interface ProfileEnrichmentServiceInterface
{
    /** @return array<string, mixed> profile summary (id, name, graduation year, program) */
    public function getProfileSummary(string $personId): array;

    /** @return list<array<string, mixed>> LinkedIn candidate cards for confirmation */
    public function searchLinkedInCandidates(string $personId): array;

    /** Imports a confirmed LinkedIn profile as experiences in `a_confirmer` status. */
    public function importLinkedInProfile(string $personId, string $linkedInProfileId): void;
}
