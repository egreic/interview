<?php

declare(strict_types=1);

namespace App\Module\Tenancy;

use App\Module\Tenancy\Document\Tenant;
use App\Module\Tenancy\OdmFilter\TenantFilter;
use Doctrine\ODM\MongoDB\DocumentManager;

/**
 * Holds the tenant scope of the current request/process and drives the ODM
 * tenant filter accordingly. Safe by default: with no tenant set, tenant-owned
 * queries match nothing. Cross-tenant reads require an explicit super-admin
 * scope — never a silent default.
 */
final class TenantContext
{
    public const FILTER_NAME = 'tenant';

    private ?Tenant $tenant = null;
    private bool $superAdmin = false;

    public function __construct(private readonly DocumentManager $dm)
    {
    }

    public function setCurrentTenant(Tenant $tenant): void
    {
        if (null === $tenant->getId()) {
            throw new \LogicException('Cannot scope to an unpersisted tenant.');
        }

        $this->tenant = $tenant;
        $this->superAdmin = false;
        $this->filter()->setParameter('tenantId', $tenant->getId());
    }

    /** Back to the default deny-all scope. */
    public function clear(): void
    {
        $this->tenant = null;
        $this->superAdmin = false;
        $this->filter()->setParameter('tenantId', TenantFilter::NO_TENANT);
    }

    /** Explicit cross-tenant scope — the only way to see all tenants. */
    public function actAsSuperAdmin(): void
    {
        $this->tenant = null;
        $this->superAdmin = true;

        $filters = $this->dm->getFilterCollection();
        if ($filters->isEnabled(self::FILTER_NAME)) {
            $filters->disable(self::FILTER_NAME);
        }
    }

    public function getCurrentTenant(): ?Tenant
    {
        return $this->tenant;
    }

    public function getCurrentTenantId(): ?string
    {
        return $this->tenant?->getId();
    }

    public function isSuperAdmin(): bool
    {
        return $this->superAdmin;
    }

    private function filter(): TenantFilter
    {
        $filters = $this->dm->getFilterCollection();
        if (!$filters->isEnabled(self::FILTER_NAME)) {
            $filters->enable(self::FILTER_NAME);
        }

        $filter = $filters->getFilter(self::FILTER_NAME);
        \assert($filter instanceof TenantFilter);

        return $filter;
    }
}
