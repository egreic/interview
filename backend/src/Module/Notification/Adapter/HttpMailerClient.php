<?php

declare(strict_types=1);

namespace App\Module\Notification\Adapter;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * studizz-api-mailer HTTP client. The gateway itself is unauthenticated
 * (internal network); provider credentials travel inside the payload.
 */
final class HttpMailerClient implements MailerClientInterface
{
    private readonly string $baseUrl;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[Autowire(env: 'STUDIZZ_API_MAILER_URL')]
        string $baseUrl,
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    public function sendMail(array $payload): array
    {
        return $this->post('/mail', $payload);
    }

    public function sendSms(array $payload): array
    {
        return $this->post('/sms/send', $payload);
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    private function post(string $path, array $payload): array
    {
        $response = $this->httpClient->request('POST', $this->baseUrl.$path, ['json' => $payload]);

        return $response->toArray(false);
    }
}
