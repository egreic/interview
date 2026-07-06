<?php

declare(strict_types=1);

namespace App\Module\SchoolContext\Contract;

/**
 * Brick contract for the four consumers (template generator, engine, judge,
 * avatars "today" mode). Rule: school context NEVER contradicts the
 * interviewee — divergence is tagged `historique` for review. Implemented at J2+.
 */
interface SchoolContextServiceInterface
{
    /** @return array<string, mixed>|null the latest validated sheet payload */
    public function getValidatedContext(): ?array;

    /** Version identifier to stamp on interview records. */
    public function getValidatedContextVersion(): ?string;
}
