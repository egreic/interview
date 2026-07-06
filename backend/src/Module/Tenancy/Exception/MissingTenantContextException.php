<?php

declare(strict_types=1);

namespace App\Module\Tenancy\Exception;

final class MissingTenantContextException extends \RuntimeException
{
    public static function forDocument(object $document): self
    {
        return new self(sprintf(
            'Refusing to persist tenant-owned document %s without a tenant in context. Set a tenant via TenantContext (or stamp the tenantId explicitly as super-admin).',
            $document::class,
        ));
    }
}
