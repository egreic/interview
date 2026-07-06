<?php

declare(strict_types=1);

namespace App\Module\Security\StudizzAuth;

/**
 * Dev/test stub — the shared dev studizz-auth instance is not reachable from
 * every environment. Produces structurally valid, UNSIGNED JWTs the decoder
 * accepts (the reference integration trusts transport, not signatures).
 */
final class StubStudizzAuthClient implements StudizzAuthClientInterface
{
    public function loginCheck(string $email, #[\SensitiveParameter] string $password): array
    {
        return [
            'token' => self::buildJwt($email, ['ROLE_USER', 'ROLE_ADMIN'], 3600),
            'refresh_token' => 'stub-refresh-'.bin2hex(random_bytes(8)),
        ];
    }

    public function refreshToken(string $refreshToken): array
    {
        return [
            'token' => self::buildJwt('stub-admin@studizz.fr', ['ROLE_USER', 'ROLE_ADMIN'], 3600),
            'refresh_token' => 'stub-refresh-'.bin2hex(random_bytes(8)),
        ];
    }

    /** @param list<string> $roles */
    public static function buildJwt(string $email, array $roles, int $ttlSeconds): string
    {
        $encode = static fn (array $data): string => rtrim(strtr(base64_encode(json_encode($data, \JSON_THROW_ON_ERROR)), '+/', '-_'), '=');

        $header = $encode(['alg' => 'none', 'typ' => 'JWT']);
        $payload = $encode([
            'email' => $email,
            'username' => $email,
            'uuid' => 'stub-'.md5($email),
            'roles' => $roles,
            'exp' => time() + $ttlSeconds,
        ]);

        return $header.'.'.$payload.'.stub';
    }
}
