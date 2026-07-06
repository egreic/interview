<?php

declare(strict_types=1);

namespace App\Module\Platform\Seed;

/**
 * Each module owns its demo seeds (a module never touches another module's
 * ODM documents — contract invariant #4). DemoSeeder runs them by ascending
 * priority.
 */
interface ModuleSeederInterface
{
    /** Lower runs first (tenants before tenant-owned data). */
    public function getPriority(): int;

    public function seed(): void;
}
