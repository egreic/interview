<?php

declare(strict_types=1);

namespace App\Module\ProfileEnrichment\Seed;

use App\Module\Platform\Seed\ModuleSeederInterface;
use App\Module\ProfileEnrichment\Document\Person;
use App\Module\Tenancy\Document\Tenant;
use App\Module\Tenancy\TenantContext;
use Doctrine\ODM\MongoDB\DocumentManager;

/**
 * Seeds fictional alumni per tenant — École Démo includes the anonymized
 * Sacha reference case (CDC §8). Runs inside each tenant's context so the
 * guard stamps tenantId (seeds exercise the same path as production code).
 */
final class PersonSeeder implements ModuleSeederInterface
{
    private const PEOPLE = [
        'ecole-demo' => [
            ['Sacha', 'D.', 'sacha.d@ecole-demo.example', 2021],
            ['Lina', 'M.', 'lina.m@ecole-demo.example', 2019],
            ['Karim', 'B.', 'karim.b@ecole-demo.example', 2022],
        ],
        'institut-nord' => [
            ['Noé', 'R.', 'noe.r@institut-nord.example', 2020],
            ['Awa', 'S.', 'awa.s@institut-nord.example', 2023],
        ],
    ];

    public function __construct(
        private readonly DocumentManager $dm,
        private readonly TenantContext $context,
    ) {
    }

    public function getPriority(): int
    {
        return 10;
    }

    public function seed(): void
    {
        $tenants = $this->dm->getRepository(Tenant::class);

        foreach (self::PEOPLE as $slug => $people) {
            $tenant = $tenants->findOneBy(['slug' => $slug]);
            if (null === $tenant) {
                throw new \RuntimeException(sprintf('Tenant "%s" must be seeded before people.', $slug));
            }

            $this->context->setCurrentTenant($tenant);

            $repository = $this->dm->getRepository(Person::class);
            foreach ($people as [$firstName, $lastName, $email, $year]) {
                if (null === $repository->findOneBy(['email' => $email])) {
                    $this->dm->persist(new Person($firstName, $lastName, $email, $year));
                }
            }

            $this->dm->flush();
        }

        $this->context->clear();
    }
}
