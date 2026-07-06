<?php

declare(strict_types=1);

namespace App\Module\Campaign\Contract;

/**
 * Brick contract — CSV import, nominative invitations + public campaign link,
 * scheduled reminders, cost estimate. Implemented at J6.
 */
interface CampaignServiceInterface
{
    /** @return array<string, mixed> campaign progress counters (sent/opened/started/completed) */
    public function getCampaignProgress(string $campaignId): array;
}
