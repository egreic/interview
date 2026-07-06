<?php

declare(strict_types=1);

namespace App\Module\Session\Contract;

/**
 * Brick contract — session lifecycle and per-turn persistence. User commands:
 * `passer`, `reformule`, `privé`. Implemented at J2 (text) / J4 (voice, pause).
 */
interface SessionServiceInterface
{
    public function start(string $personId, string $templateId, int $templateVersion): string;

    /** Persists the turn BEFORE the engine answers (continuous save invariant). */
    public function appendTurn(string $sessionId, string $role, string $content, array $flags = []): void;

    public function pause(string $sessionId): void;

    public function resume(string $sessionId): void;
}
