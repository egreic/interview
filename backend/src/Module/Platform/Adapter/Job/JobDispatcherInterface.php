<?php

declare(strict_types=1);

namespace App\Module\Platform\Adapter\Job;

/**
 * Async decision (§0.7): the backend never speaks AMQP — it publishes through
 * this interface, implemented on the studizz-api-amqp HTTP gateway; Python
 * pika workers consume downstream. "Dispatched" means accepted by the
 * gateway, NOT processed — confirmations happen out-of-band.
 */
interface JobDispatcherInterface
{
    /** Background task → exchange `async-tasks`. @param array<string, mixed> $payload */
    public function dispatchTask(array $payload): void;

    /** Async external call / webhook → exchange `async-call`. @param array<string, mixed> $payload */
    public function dispatchCall(array $payload): void;
}
