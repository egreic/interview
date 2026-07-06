<?php

declare(strict_types=1);

namespace App\Module\Platform\Adapter\Llm;

/** Dev/test stub — deterministic canned answer, no network. */
final class StubLlmClient implements LlmClientInterface
{
    public function complete(ModelTier $tier, string $system, array $messages): string
    {
        return sprintf('[stub %s-tier completion for %d message(s)]', $tier->value, \count($messages));
    }
}
