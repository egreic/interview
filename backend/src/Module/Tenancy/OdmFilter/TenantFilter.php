<?php

declare(strict_types=1);

namespace App\Module\Tenancy\OdmFilter;

use App\Module\Tenancy\TenantOwnedInterface;
use Doctrine\ODM\MongoDB\Mapping\ClassMetadata;
use Doctrine\ODM\MongoDB\Query\Filter\BsonFilter;

/**
 * ODM-level tenant guard: every query on a tenant-owned document is
 * constrained to the tenant set by TenantContext. Without a tenant, the
 * criteria match nothing (NO_TENANT sentinel) — never everything.
 */
final class TenantFilter extends BsonFilter
{
    public const NO_TENANT = '__no_tenant__';

    public function addFilterCriteria(ClassMetadata $class): array
    {
        if (!$class->reflClass->implementsInterface(TenantOwnedInterface::class)) {
            return [];
        }

        try {
            $tenantId = $this->getParameter('tenantId');
        } catch (\InvalidArgumentException) {
            $tenantId = self::NO_TENANT;
        }

        return ['tenantId' => \is_string($tenantId) ? $tenantId : self::NO_TENANT];
    }
}
