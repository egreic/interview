<?php

declare(strict_types=1);

namespace App\Module\Tenancy;

use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;

trait TenantOwnedTrait
{
    #[ODM\Field(type: 'string')]
    #[ODM\Index]
    private ?string $tenantId = null;

    public function getTenantId(): ?string
    {
        return $this->tenantId;
    }

    public function setTenantId(string $tenantId): void
    {
        $this->tenantId = $tenantId;
    }
}
