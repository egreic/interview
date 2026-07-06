<?php

declare(strict_types=1);

namespace App\Module\Platform\Adapter\Job;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * studizz-api-amqp client. No authentication by design — the gateway lives on
 * the internal network only and is NEVER exposed publicly (deployment
 * checklist). Empty payloads are refused client-side (the gateway 400s them).
 */
final class AmqpGatewayJobDispatcher implements JobDispatcherInterface
{
    private readonly string $baseUrl;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[Autowire(env: 'STUDIZZ_API_AMQP_URL')]
        string $baseUrl,
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    public function dispatchTask(array $payload): void
    {
        $this->post('/api/task', $payload);
    }

    public function dispatchCall(array $payload): void
    {
        $this->post('/api/call', $payload);
    }

    /** @param array<string, mixed> $payload */
    private function post(string $path, array $payload): void
    {
        if ([] === $payload) {
            throw new \InvalidArgumentException('Refusing to publish an empty payload to the AMQP gateway.');
        }

        $response = $this->httpClient->request('POST', $this->baseUrl.$path, ['json' => $payload]);
        if (200 !== $response->getStatusCode()) {
            throw new \RuntimeException(sprintf(
                'AMQP gateway refused the message (%s %d): %s',
                $path,
                $response->getStatusCode(),
                $response->getContent(false),
            ));
        }
    }
}
