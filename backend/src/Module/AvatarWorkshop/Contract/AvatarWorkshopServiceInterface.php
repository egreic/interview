<?php

declare(strict_types=1);

namespace App\Module\AvatarWorkshop\Contract;

/**
 * Brick contract — self-test, tone by example, tricolor patching, handoff.
 * Implemented at J5.
 */
interface AvatarWorkshopServiceInterface
{
    /** @return array<string, mixed> avatar summary (status, completeness, grey zones) */
    public function getAvatarSummary(string $avatarId): array;

    /** Submits a natural-language patch; returns the created patch id (tricolor-classified). */
    public function submitPersonaPatch(string $avatarId, string $instruction): string;
}
