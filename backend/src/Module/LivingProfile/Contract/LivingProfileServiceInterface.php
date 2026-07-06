<?php

declare(strict_types=1);

namespace App\Module\LivingProfile\Contract;

/**
 * Brick contract — metadata-driven chunk queries. Conflict rule: author
 * correction > person-validated > most recent. V1 is single-interview;
 * multi-source aggregation is modeled but activated in phase 2. J2.
 */
interface LivingProfileServiceInterface
{
    /** @param array<string, mixed> $criteria (year, experienceId, theme, visibility…)
     *  @return list<array<string, mixed>> */
    public function findChunks(string $personId, array $criteria = []): array;
}
