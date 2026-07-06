# PHP client for studizz-api-amqp

Language-specific guide. Read `api-spec.md` first.

## Which HTTP client?

Pick whichever is already a dependency of the target project. In order of preference when the project has a free choice:

1. **Symfony HttpClient** (`symfony/http-client`) — best fit if the project is already a Symfony app or uses other Symfony components. Zero extra dependencies in that case.
2. **Guzzle** (`guzzlehttp/guzzle`) — ubiquitous, fine for any PHP project.
3. **`curl` via `file_get_contents` / `curl_*`** — only if the project explicitly avoids Composer dependencies. Rare.

If `composer.json` already has one, use it. Do not add a second HTTP client.

## Configuration

Expose the base URL through an env var:

```
# .env
STUDIZZ_API_AMQP_URL=https://<host>
```

In Symfony, bind it as a parameter and inject it:

```yaml
# config/services.yaml
parameters:
    studizz_amqp_api_url: '%env(STUDIZZ_API_AMQP_URL)%'

services:
    App\Client\StudizzAmqpClient:
        arguments:
            $baseUrl: '%studizz_amqp_api_url%'
```

In plain PHP, read it with `getenv('STUDIZZ_API_AMQP_URL')` or `$_ENV['STUDIZZ_API_AMQP_URL']` (after loading `.env` with `vlucas/phpdotenv` or similar).

## Reference implementation — Symfony HttpClient

File: `src/Client/StudizzAmqpClient.php`

```php
<?php

declare(strict_types=1);

namespace App\Client;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;

final class StudizzAmqpClient
{
    private string $baseUrl;

    public function __construct(
        private HttpClientInterface $httpClient,
        string $baseUrl,
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    /** Publish a JSON payload to the `async-tasks` exchange. */
    public function publishTask(array $payload): void
    {
        $this->post('/api/task', $payload);
    }

    /** Publish a JSON payload to the `async-call` exchange. */
    public function publishCall(array $payload): void
    {
        $this->post('/api/call', $payload);
    }

    private function post(string $path, array $payload): void
    {
        if ($payload === []) {
            throw new \InvalidArgumentException('Studizz AMQP payload cannot be empty.');
        }

        try {
            $response = $this->httpClient->request('POST', $this->baseUrl . $path, [
                'headers' => ['Content-Type' => 'application/json'],
                'body'    => json_encode($payload, JSON_THROW_ON_ERROR),
            ]);
            $status = $response->getStatusCode();
        } catch (ExceptionInterface $e) {
            throw new \RuntimeException(
                sprintf('Studizz AMQP call to %s failed: %s', $path, $e->getMessage()),
                previous: $e,
            );
        }

        if ($status !== 200) {
            throw new \RuntimeException(sprintf(
                'Studizz AMQP call to %s returned HTTP %d: %s',
                $path, $status, $response->getContent(false),
            ));
        }
    }
}
```

Usage:

```php
$client->publishTask([
    'type'     => 'send_email',
    'to'       => 'user@example.com',
    'template' => 'welcome',
]);
```

## Reference implementation — Guzzle

```php
<?php

declare(strict_types=1);

namespace App\Client;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

final class StudizzAmqpClient
{
    private string $baseUrl;

    public function __construct(private Client $http, string $baseUrl)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    public function publishTask(array $payload): void { $this->post('/api/task', $payload); }
    public function publishCall(array $payload): void { $this->post('/api/call', $payload); }

    private function post(string $path, array $payload): void
    {
        if ($payload === []) {
            throw new \InvalidArgumentException('Studizz AMQP payload cannot be empty.');
        }

        try {
            $response = $this->http->post($this->baseUrl . $path, [
                'headers' => ['Content-Type' => 'application/json'],
                'json'    => $payload,
            ]);
        } catch (GuzzleException $e) {
            throw new \RuntimeException(
                sprintf('Studizz AMQP call to %s failed: %s', $path, $e->getMessage()),
                previous: $e,
            );
        }

        if ($response->getStatusCode() !== 200) {
            throw new \RuntimeException(sprintf(
                'Studizz AMQP call to %s returned HTTP %d: %s',
                $path, $response->getStatusCode(), (string) $response->getBody(),
            ));
        }
    }
}
```

## Install commands

Only run if the chosen client is not already in `composer.json`.

```bash
# Symfony HttpClient
composer require symfony/http-client

# Guzzle
composer require guzzlehttp/guzzle
```

## Gotchas

- **`json_encode` with `JSON_THROW_ON_ERROR`**: prevents silent encoding failures on non-UTF-8 input. Worth the one extra flag.
- **Don't use `Accept: application/json`** — not needed, the gateway always returns JSON.
- **Symfony HttpClient is lazy**: the request is not actually sent until you touch the response (e.g. `getStatusCode()`). The code above does, so it behaves synchronously. If you refactor, keep that in mind.
