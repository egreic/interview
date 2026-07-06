<?php

declare(strict_types=1);

namespace App\Module\Security\MagicLink;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Signed, TTL-bound magic-link tokens — the auth mechanism for interviewees
 * and alumni (non-negotiable, arbitration #3). Scopes separate usages
 * (invitation TTL 7 days, personal space, sensitive actions); the exchange
 * for a limited-scope JWT happens at the API boundary. Single-use enforcement
 * for critical actions (a used-token registry) lands at J3 with the
 * invitation flow.
 *
 * Token format: base64url(claims JSON).base64url(HMAC-SHA256).
 */
final class MagicLinkTokenService
{
    public const SCOPE_INTERVIEW = 'interview';
    public const SCOPE_PERSONAL_SPACE = 'personal_space';

    public function __construct(
        #[Autowire(env: 'MAGIC_LINK_SECRET')]
        private readonly string $secret,
    ) {
        if ('' === $secret) {
            throw new \InvalidArgumentException('MAGIC_LINK_SECRET must not be empty.');
        }
    }

    public function issue(string $subject, string $scope, \DateInterval $ttl): string
    {
        $now = new \DateTimeImmutable();
        $claims = [
            'sub' => $subject,
            'scope' => $scope,
            'iat' => $now->getTimestamp(),
            'exp' => $now->add($ttl)->getTimestamp(),
            'jti' => bin2hex(random_bytes(16)),
        ];

        $payload = $this->base64UrlEncode(json_encode($claims, \JSON_THROW_ON_ERROR));

        return $payload.'.'.$this->sign($payload);
    }

    /** @throws InvalidMagicLinkException */
    public function verify(string $token, string $expectedScope): MagicLinkClaims
    {
        $parts = explode('.', $token);
        if (2 !== \count($parts)) {
            throw new InvalidMagicLinkException('Malformed magic-link token.');
        }
        [$payload, $signature] = $parts;

        if (!hash_equals($this->sign($payload), $signature)) {
            throw new InvalidMagicLinkException('Invalid magic-link signature.');
        }

        try {
            $claims = json_decode($this->base64UrlDecode($payload), true, 8, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new InvalidMagicLinkException('Unreadable magic-link claims.');
        }

        if (!\is_array($claims) || !isset($claims['sub'], $claims['scope'], $claims['exp'], $claims['jti'])) {
            throw new InvalidMagicLinkException('Incomplete magic-link claims.');
        }

        if ($claims['scope'] !== $expectedScope) {
            throw new InvalidMagicLinkException(sprintf('Magic-link scope "%s" does not match expected "%s".', $claims['scope'], $expectedScope));
        }

        $expiresAt = (new \DateTimeImmutable())->setTimestamp((int) $claims['exp']);
        if ($expiresAt <= new \DateTimeImmutable()) {
            throw new InvalidMagicLinkException('Expired magic link.');
        }

        return new MagicLinkClaims((string) $claims['sub'], (string) $claims['scope'], $expiresAt, (string) $claims['jti']);
    }

    private function sign(string $payload): string
    {
        return $this->base64UrlEncode(hash_hmac('sha256', $payload, $this->secret, true));
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $data): string
    {
        $decoded = base64_decode(strtr($data, '-_', '+/'), true);
        if (false === $decoded) {
            throw new InvalidMagicLinkException('Invalid base64url payload.');
        }

        return $decoded;
    }
}
