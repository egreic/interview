<?php

declare(strict_types=1);

namespace App\Tests\Acceptance;

use App\Module\ProfileEnrichment\Document\Person;
use App\Module\Platform\Seed\DemoSeeder;
use App\Module\Tenancy\Document\Tenant;
use App\Module\Tenancy\Exception\MissingTenantContextException;
use App\Module\Tenancy\TenantContext;
use Doctrine\ODM\MongoDB\DocumentManager;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * J1 acceptance criterion: two tenants are seeded and ODM-level tenant
 * isolation is proven by test — a query made in tenant A's context can never
 * see tenant B's documents, no tenant context yields no data and refuses
 * writes, and cross-tenant reads require an explicit super-admin scope.
 *
 * Requires ext-mongodb and a running MongoDB (mongo:7 service in CI).
 */
final class TenantIsolationTest extends KernelTestCase
{
    protected function setUp(): void
    {
        if (!\extension_loaded('mongodb')) {
            self::markTestSkipped('ext-mongodb unavailable in this environment — this suite runs in CI against mongo:7.');
        }

        self::bootKernel();
        $dm = self::getContainer()->get(DocumentManager::class);
        $dm->getDocumentDatabase(Tenant::class)->drop();
        $dm->getDocumentDatabase(Person::class)->drop();

        self::getContainer()->get(DemoSeeder::class)->seed();
        $dm->clear();
        $this->context()->clear();
    }

    public function testTwoTenantsAreSeeded(): void
    {
        $slugs = array_map(
            static fn (Tenant $tenant): string => $tenant->getSlug(),
            $this->dm()->getRepository(Tenant::class)->findAll(),
        );
        sort($slugs);

        self::assertSame(['ecole-demo', 'institut-nord'], $slugs);
    }

    public function testEachTenantOnlySeesItsOwnDocuments(): void
    {
        [$demo, $nord] = $this->twoTenants();

        $this->context()->setCurrentTenant($demo);
        $demoPeople = $this->dm()->getRepository(Person::class)->findAll();
        self::assertNotEmpty($demoPeople, 'Tenant "ecole-demo" must have seeded people.');
        foreach ($demoPeople as $person) {
            self::assertSame($demo->getId(), $person->getTenantId());
        }

        $this->dm()->clear();
        $this->context()->setCurrentTenant($nord);
        $nordPeople = $this->dm()->getRepository(Person::class)->findAll();
        self::assertNotEmpty($nordPeople, 'Tenant "institut-nord" must have seeded people.');
        foreach ($nordPeople as $person) {
            self::assertSame($nord->getId(), $person->getTenantId());
        }

        $demoIds = array_map(static fn (Person $p): string => (string) $p->getId(), $demoPeople);
        $nordIds = array_map(static fn (Person $p): string => (string) $p->getId(), $nordPeople);
        self::assertSame([], array_intersect($demoIds, $nordIds), 'No document may be visible from both tenants.');
    }

    public function testQueryingWithoutTenantContextYieldsNothing(): void
    {
        $this->context()->clear();

        self::assertSame([], $this->dm()->getRepository(Person::class)->findAll());
    }

    public function testPersistingWithoutTenantContextIsRefused(): void
    {
        $this->context()->clear();

        $person = new Person('Testy', 'McTest', 'testy@example.test');
        $this->dm()->persist($person);

        $this->expectException(MissingTenantContextException::class);
        $this->dm()->flush();
    }

    public function testExplicitSuperAdminScopeSeesAllTenants(): void
    {
        [$demo, $nord] = $this->twoTenants();

        $this->context()->setCurrentTenant($demo);
        $demoCount = \count($this->dm()->getRepository(Person::class)->findAll());
        $this->dm()->clear();
        $this->context()->setCurrentTenant($nord);
        $nordCount = \count($this->dm()->getRepository(Person::class)->findAll());
        $this->dm()->clear();

        $this->context()->actAsSuperAdmin();
        $all = $this->dm()->getRepository(Person::class)->findAll();

        self::assertSame($demoCount + $nordCount, \count($all));
        self::assertGreaterThan(0, $demoCount);
        self::assertGreaterThan(0, $nordCount);
    }

    /** @return array{0: Tenant, 1: Tenant} */
    private function twoTenants(): array
    {
        $repository = $this->dm()->getRepository(Tenant::class);
        $demo = $repository->findOneBy(['slug' => 'ecole-demo']);
        $nord = $repository->findOneBy(['slug' => 'institut-nord']);
        self::assertInstanceOf(Tenant::class, $demo);
        self::assertInstanceOf(Tenant::class, $nord);

        return [$demo, $nord];
    }

    private function dm(): DocumentManager
    {
        return self::getContainer()->get(DocumentManager::class);
    }

    private function context(): TenantContext
    {
        return self::getContainer()->get(TenantContext::class);
    }
}
