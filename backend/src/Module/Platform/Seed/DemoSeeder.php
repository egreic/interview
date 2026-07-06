<?php

declare(strict_types=1);

namespace App\Module\Platform\Seed;

/**
 * Runs every tagged module seeder in priority order. Idempotence is each
 * seeder's responsibility (skip when its data already exists).
 */
final class DemoSeeder
{
    /** @var list<ModuleSeederInterface> */
    private readonly array $seeders;

    /** @param iterable<ModuleSeederInterface> $seeders */
    public function __construct(iterable $seeders)
    {
        $sorted = [...$seeders];
        usort($sorted, static fn (ModuleSeederInterface $a, ModuleSeederInterface $b): int => $a->getPriority() <=> $b->getPriority());
        $this->seeders = $sorted;
    }

    public function seed(): void
    {
        foreach ($this->seeders as $seeder) {
            $seeder->seed();
        }
    }
}
