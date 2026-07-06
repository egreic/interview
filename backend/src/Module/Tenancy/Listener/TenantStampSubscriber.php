<?php

declare(strict_types=1);

namespace App\Module\Tenancy\Listener;

use App\Module\Tenancy\Exception\MissingTenantContextException;
use App\Module\Tenancy\Exception\TenantMismatchException;
use App\Module\Tenancy\TenantContext;
use App\Module\Tenancy\TenantOwnedInterface;
use Doctrine\Common\EventSubscriber;
use Doctrine\ODM\MongoDB\Event\LifecycleEventArgs;
use Doctrine\ODM\MongoDB\Events;

/**
 * Write-side of the tenant guard: stamps the current tenant on new documents
 * and refuses writes that have no tenant or contradict the context.
 */
final class TenantStampSubscriber implements EventSubscriber
{
    public function __construct(private readonly TenantContext $context)
    {
    }

    public function getSubscribedEvents(): array
    {
        return [Events::prePersist];
    }

    public function prePersist(LifecycleEventArgs $args): void
    {
        $document = $args->getDocument();
        if (!$document instanceof TenantOwnedInterface) {
            return;
        }

        $contextTenantId = $this->context->getCurrentTenantId();
        $documentTenantId = $document->getTenantId();

        if (null === $documentTenantId) {
            if (null === $contextTenantId) {
                throw MissingTenantContextException::forDocument($document);
            }
            $document->setTenantId($contextTenantId);

            return;
        }

        if ($this->context->isSuperAdmin()) {
            return; // explicit cross-tenant scope may stamp any tenant
        }

        if ($documentTenantId !== $contextTenantId) {
            throw TenantMismatchException::forDocument($document, $documentTenantId, $contextTenantId ?? '(none)');
        }
    }
}
