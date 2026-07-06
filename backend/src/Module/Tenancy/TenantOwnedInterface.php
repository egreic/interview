<?php

declare(strict_types=1);

namespace App\Module\Tenancy;

/**
 * Every document belonging to a tenant implements this. The ODM tenant filter
 * and the persist guard key on it — a document that does not implement it is
 * platform-level (e.g. Tenant itself).
 */
interface TenantOwnedInterface
{
    public function getTenantId(): ?string;

    public function setTenantId(string $tenantId): void;
}
