<?php

declare(strict_types=1);

namespace App\Module\InterviewEngine\Dto;

/**
 * One engine answer. Conversational replies split on "\n" — one line, one
 * bubble, max 3 (display adds the typing delay).
 */
final readonly class EngineReply
{
    /** @param list<string> $bubbles @param array<string, float> $completenessScores */
    public function __construct(
        public array $bubbles,
        public array $completenessScores = [],
        public bool $themeCompleted = false,
    ) {
    }
}
