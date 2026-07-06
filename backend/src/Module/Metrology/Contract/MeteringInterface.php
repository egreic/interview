<?php

declare(strict_types=1);

namespace App\Module\Metrology\Contract;

/** Transverse contract — every provider call is metered. */
interface MeteringInterface
{
    public function count(string $kind, float $quantity, ?string $sessionId = null, ?string $campaignId = null): void;
}
