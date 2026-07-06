<?php

declare(strict_types=1);

namespace App\Module\Platform\Adapter\Llm;

/**
 * LLM adapter (§0.3 hypothesis: Claude by default). Consumers must parse
 * JSON answers tolerantly (strip code fences, fall back to "doute") — never
 * crash on a judge reply.
 */
interface LlmClientInterface
{
    /** @param list<array{role: string, content: string}> $messages */
    public function complete(ModelTier $tier, string $system, array $messages): string;
}
