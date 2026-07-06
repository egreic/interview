<?php

declare(strict_types=1);

namespace App\Module\Platform\Adapter\Job;

/** Dev/test stub — collects payloads in memory. */
final class StubJobDispatcher implements JobDispatcherInterface
{
    /** @var list<array<string, mixed>> */
    public array $tasks = [];

    /** @var list<array<string, mixed>> */
    public array $calls = [];

    public function dispatchTask(array $payload): void
    {
        $this->tasks[] = $payload;
    }

    public function dispatchCall(array $payload): void
    {
        $this->calls[] = $payload;
    }
}
