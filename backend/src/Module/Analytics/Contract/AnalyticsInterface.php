<?php

declare(strict_types=1);

namespace App\Module\Analytics\Contract;

/** Transverse contract — every significant state transition tracks an event. */
interface AnalyticsInterface
{
    /** @param array<string, mixed> $payload */
    public function track(string $eventName, array $payload = []): void;
}
