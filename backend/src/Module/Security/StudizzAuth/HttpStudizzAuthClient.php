<?php

declare(strict_types=1);

namespace App\Module\Security\StudizzAuth;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * HTTP implementation of the studizz-auth contract. `login_check` and
 * `token/refresh` are form-encoded (NOT JSON) per the service contract; the
 * base URL must keep its trailing slash (relative paths are joined to it).
 */
final class HttpStudizzAuthClient implements StudizzAuthClientInterface
{
    private readonly string $baseUrl;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[Autowire(env: 'STUDIZZ_AUTH_URL')]
        string $baseUrl,
        #[Autowire(env: 'STUDIZZ_AUTH_KEY')]
        private readonly string $apiKey,
    ) {
        $this->baseUrl = rtrim($baseUrl, '/').'/';
    }

    public function loginCheck(string $email, #[\SensitiveParameter] string $password): array
    {
        return $this->postForm('login_check', ['_username' => $email, '_password' => $password]);
    }

    public function refreshToken(string $refreshToken): array
    {
        return $this->postForm('token/refresh', ['refreshToken' => $refreshToken]);
    }

    /** @param array<string, string> $form @return array{token: string, refresh_token: string} */
    private function postForm(string $path, array $form): array
    {
        $response = $this->httpClient->request('POST', $this->baseUrl.$path, [
            'headers' => ['x-api-key' => $this->apiKey],
            'body' => $form,
        ]);

        /** @var array{token: string, refresh_token: string} */
        return $response->toArray();
    }
}
