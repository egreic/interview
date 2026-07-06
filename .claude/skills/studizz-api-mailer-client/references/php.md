# PHP client for studizz-api-mailer

Read `api-spec.md` first. This file shows idiomatic PHP integration patterns.

## Which HTTP client?

Reuse whatever the project already depends on:

1. **Symfony HttpClient** (`symfony/http-client`) — first choice if the project is already a Symfony app or uses other Symfony components.
2. **Guzzle** (`guzzlehttp/guzzle`) — ubiquitous, fine for any PHP project (Laravel, plain PHP, legacy).
3. **`curl_*` functions** — only if the project explicitly avoids Composer HTTP dependencies.

If `composer.json` already lists one, use it. Don't add a second HTTP client just for this.

## Configuration

Expose the base URL through an env var. Use the canonical name — every existing Studizz project does:

```
# .env
STUDIZZ_API_MAILER_URL=https://<mailer-host>
```

If the project sends SMS, also store provider credentials in `.env`:

```
# .env  (OVH SMS — most common)
OVH_SMS_ACCOUNT=sms-xxx-1
OVH_SMS_LOGIN=ovh-user-login
OVH_SMS_PASSWORD=ovh-user-password
```

In Symfony, bind as parameters and inject:

```yaml
# config/services.yaml
parameters:
    studizz_api_mailer_url: '%env(STUDIZZ_API_MAILER_URL)%'

services:
    App\Client\StudizzMailerClient:
        arguments:
            $baseUrl: '%studizz_api_mailer_url%'
```

In plain PHP, read with `getenv('STUDIZZ_API_MAILER_URL')` or `$_ENV['STUDIZZ_API_MAILER_URL']` (after loading `.env` via `vlucas/phpdotenv`).

## Reference implementation — Symfony HttpClient

File: `src/Client/StudizzMailerClient.php`

```php
<?php

declare(strict_types=1);

namespace App\Client;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;

final class StudizzMailerClient
{
    private string $baseUrl;

    public function __construct(
        private HttpClientInterface $httpClient,
        string $baseUrl,
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    /**
     * Send a transactional email via Mailjet (default) or AWS SES.
     *
     * @param array $payload See api-spec.md — required: subject, htmlPart, fromEmail, fromName, recipients.
     * @return array Decoded response body. Always check $body['code'].
     */
    public function sendMail(array $payload): array
    {
        return $this->post('/mail', $payload);
    }

    /**
     * Send an SMS via OVH, Primotexto, or 123SMS.
     *
     * @param array $payload See api-spec.md — required: provider, text, from, to, credentials.
     * @return array Decoded response body. Always check $body['code'].
     */
    public function sendSms(array $payload): array
    {
        return $this->post('/sms/send', $payload);
    }

    private function post(string $path, array $payload): array
    {
        try {
            $response = $this->httpClient->request('POST', $this->baseUrl . $path, [
                'json'    => $payload,
                'headers' => ['Accept' => 'application/json'],
                'timeout' => 15,
            ]);

            $body = $response->toArray(false);
            $status = $response->getStatusCode();
        } catch (ExceptionInterface $e) {
            throw new \RuntimeException(
                sprintf('studizz-api-mailer transport failure on %s: %s', $path, $e->getMessage()),
                0,
                $e,
            );
        }

        // The gateway often returns HTTP 200/201 with an inner code like 400.
        // Trust $body['code'] for the real outcome.
        $innerCode = $body['code'] ?? $status;
        if ($innerCode >= 400) {
            throw new \RuntimeException(sprintf(
                'studizz-api-mailer rejected %s (http=%d, code=%d): %s',
                $path,
                $status,
                $innerCode,
                $body['message'] ?? json_encode($body),
            ));
        }

        return $body;
    }
}
```

## Usage — sending a mail (Mailjet, default)

```php
$client->sendMail([
    'subject'    => 'Bienvenue chez Studizz',
    'htmlPart'   => '<p>Hi {{var:firstName}}, welcome!</p>',
    'fromEmail'  => 'contact@studizz.fr',
    'fromName'   => 'Studizz',
    'recipients' => [
        ['Email' => 'jane@example.com', 'Name' => 'Jane Doe', 'Vars' => ['firstName' => 'Jane']],
    ],
]);
```

## Usage — sending a mail (AWS SES)

```php
$client->sendMail([
    'provider'   => 'aws',
    'subject'    => 'Confirmation',
    'htmlPart'   => '<p>Hi [[var:firstName]], your code is [[var:code]].</p>',
    'fromEmail'  => 'no-reply@studizz.fr',
    'fromName'   => 'Studizz',
    'recipients' => [
        ['Email' => 'jane@example.com', 'Vars' => ['firstName' => 'Jane', 'code' => '4821']],
    ],
]);
```

> AWS SES uses the `[[var:name]]` placeholder syntax (not `{{var:name}}`).

## Usage — sending an SMS (OVH HTTP)

```php
$client->sendSms([
    'provider'   => 'ovh',
    'text'       => 'Votre code Studizz : 4821',
    'from'       => 'Studizz',
    'to'         => ['+33612345678'],
    'credentials' => [
        'smsAccount' => $_ENV['OVH_SMS_ACCOUNT'],
        'login'      => $_ENV['OVH_SMS_LOGIN'],
        'password'   => $_ENV['OVH_SMS_PASSWORD'],
    ],
]);
```

## Reference implementation — Guzzle

When Symfony HttpClient is not available, the body is the same — only the transport changes:

```php
<?php

declare(strict_types=1);

namespace App\Client;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;

final class StudizzMailerClient
{
    private string $baseUrl;

    public function __construct(
        private ClientInterface $httpClient,
        string $baseUrl,
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    public function sendMail(array $payload): array { return $this->post('/mail', $payload); }
    public function sendSms(array $payload): array  { return $this->post('/sms/send', $payload); }

    private function post(string $path, array $payload): array
    {
        try {
            $response = $this->httpClient->request('POST', $this->baseUrl . $path, [
                'json'        => $payload,
                'headers'     => ['Accept' => 'application/json'],
                'http_errors' => false,
                'timeout'     => 15,
            ]);
        } catch (GuzzleException $e) {
            throw new \RuntimeException("studizz-api-mailer transport failure on {$path}: " . $e->getMessage(), 0, $e);
        }

        $body = json_decode((string) $response->getBody(), true) ?? [];
        $innerCode = $body['code'] ?? $response->getStatusCode();
        if ($innerCode >= 400) {
            throw new \RuntimeException(sprintf(
                'studizz-api-mailer rejected %s (http=%d, code=%d): %s',
                $path,
                $response->getStatusCode(),
                $innerCode,
                $body['message'] ?? (string) $response->getBody(),
            ));
        }

        return $body;
    }
}
```

## Laravel notes

- Reach for the framework HTTP facade: `Http::baseUrl(config('services.studizz_mailer.url'))->post('/mail', $payload)`. Same payload shape, same response handling.
- Put base URL + provider credentials in `config/services.php` under a `studizz_mailer` key, sourced from env. Don't read `env()` outside config files in Laravel.

## Symfony-specific extras

- The existing project `studizz-api` already has a `StudizzMailerService` (`src/Service/StudizzMailerService.php`) that extends a base `StudizzService` — if you're working **inside that project**, prefer extending that pattern rather than creating a parallel client.
- For DI: bind the env var as a parameter (see above), don't `getenv()` inside the constructor.

## Mistakes to avoid

- **Do not set `Content-Type` manually** when using `'json' => $payload` — the HTTP client does it for you. Setting it twice can cause a 400.
- **Do not log the full payload as-is.** Redact `credentials.password`, `credentials.apiKey`, `credentials.pass`, and any `apiSecret` before logging.
- **Do not retry on inner-code 400.** That's a payload error (missing field, bad email, invalid recipient) — retrying won't fix it. Surface it to the caller.
- **Don't hardcode the base URL** in tests either — use the same env var and override it in `phpunit.xml.dist` / Laravel's `.env.testing`.
