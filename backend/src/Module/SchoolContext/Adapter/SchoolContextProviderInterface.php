<?php

declare(strict_types=1);

namespace App\Module\SchoolContext\Adapter;

/**
 * Adapter to the external school-context collector (out of scope). Returns
 * payloads following the §2.2 exchange contract.
 */
interface SchoolContextProviderInterface
{
    /** @return array<string, mixed> */
    public function fetchLatest(string $tenantSlug): array;
}
