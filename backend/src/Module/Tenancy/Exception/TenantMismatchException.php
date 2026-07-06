<?php

declare(strict_types=1);

namespace App\Module\Tenancy\Exception;

final class TenantMismatchException extends \RuntimeException
{
    public static function forDocument(object $document, string $documentTenantId, string $contextTenantId): self
    {
        return new self(sprintf(
            'Refusing to persist %s stamped for tenant "%s" while the context tenant is "%s".',
            $document::class,
            $documentTenantId,
            $contextTenantId,
        ));
    }
}
