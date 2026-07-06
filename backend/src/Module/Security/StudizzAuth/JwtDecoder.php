<?php

declare(strict_types=1);

namespace App\Module\Security\StudizzAuth;

/**
 * Local JWT decode — no remote introspection call, no signature validation
 * (parity with the reference integration: the token is trusted because it
 * came from the auth service over HTTPS with the project key; only `exp` is
 * checked). Payload carries email, roles, uuid, username, exp.
 */
final class JwtDecoder
{
    /** @throws InvalidJwtException */
    public function decode(string $jwt): AdminUser
    {
        $segments = explode('.', $jwt);
        if (3 !== \count($segments)) {
            throw new InvalidJwtException('Malformed JWT.');
        }

        $decoded = base64_decode(strtr($segments[1], '-_', '+/'), true);
        if (false === $decoded) {
            throw new InvalidJwtException('Invalid JWT payload encoding.');
        }

        try {
            $payload = json_decode($decoded, true, 8, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new InvalidJwtException('Unreadable JWT payload.');
        }

        if (!\is_array($payload) || !isset($payload['email'], $payload['roles'], $payload['exp'])) {
            throw new InvalidJwtException('Incomplete JWT payload.');
        }

        $expiresAt = (new \DateTimeImmutable())->setTimestamp((int) $payload['exp']);
        if ($expiresAt <= new \DateTimeImmutable()) {
            throw new InvalidJwtException('Expired JWT.');
        }

        return new AdminUser(
            email: (string) $payload['email'],
            roles: array_values(array_map(strval(...), (array) $payload['roles'])),
            uuid: (string) ($payload['uuid'] ?? ''),
            username: (string) ($payload['username'] ?? $payload['email']),
            token: $jwt,
            expiresAt: $expiresAt,
        );
    }
}
