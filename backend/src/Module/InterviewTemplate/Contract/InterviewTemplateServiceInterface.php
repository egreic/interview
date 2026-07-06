<?php

declare(strict_types=1);

namespace App\Module\InterviewTemplate\Contract;

/**
 * Brick contract — template access for campaigns and the engine. The AI
 * generator and split-view editing land at J6.
 */
interface InterviewTemplateServiceInterface
{
    /** @return array<string, mixed> a frozen template version (as referenced by a campaign) */
    public function getTemplateVersion(string $templateId, int $version): array;
}
