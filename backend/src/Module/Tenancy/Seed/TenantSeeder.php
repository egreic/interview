<?php

declare(strict_types=1);

namespace App\Module\Tenancy\Seed;

use App\Module\Platform\Seed\ModuleSeederInterface;
use App\Module\Tenancy\Document\Tenant;
use Doctrine\ODM\MongoDB\DocumentManager;

/**
 * Seeds the two reference tenants of the J1 acceptance criterion:
 * École Démo (the fixture school of the CDC §8) and Institut Nord (the
 * second tenant proving isolation).
 */
final class TenantSeeder implements ModuleSeederInterface
{
    public function __construct(private readonly DocumentManager $dm)
    {
    }

    public function getPriority(): int
    {
        return 0;
    }

    public function seed(): void
    {
        $repository = $this->dm->getRepository(Tenant::class);

        foreach ([
            ['École Démo', 'ecole-demo', '#0E7C66'],
            ['Institut Nord', 'institut-nord', '#1D4ED8'],
        ] as [$name, $slug, $accent]) {
            if (null === $repository->findOneBy(['slug' => $slug])) {
                $this->dm->persist(new Tenant($name, $slug, $accent));
            }
        }

        $this->dm->flush();
    }
}
