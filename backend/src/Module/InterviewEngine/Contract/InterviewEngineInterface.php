<?php

declare(strict_types=1);

namespace App\Module\InterviewEngine\Contract;

use App\Module\InterviewEngine\Dto\EngineReply;

/**
 * Brick contract — the conduction engine ("moteur de conduite", §2.8).
 * Assembles per-turn context (template theme + cursor + budget, confirmed
 * timeline, school context RAG, session history, completeness score) and
 * applies the versioned elicitation policy from /prompts. Implemented at J2.
 */
interface InterviewEngineInterface
{
    public function nextTurn(string $sessionId, string $userUtterance): EngineReply;
}
