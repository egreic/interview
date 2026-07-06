<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Module\Security\MagicLink\InvalidMagicLinkException;
use App\Module\Security\MagicLink\MagicLinkTokenService;
use PHPUnit\Framework\TestCase;

final class MagicLinkTokenServiceTest extends TestCase
{
    private MagicLinkTokenService $service;

    protected function setUp(): void
    {
        $this->service = new MagicLinkTokenService('unit-test-secret');
    }

    public function testIssueAndVerifyRoundTrip(): void
    {
        $token = $this->service->issue('person-42', MagicLinkTokenService::SCOPE_INTERVIEW, new \DateInterval('P7D'));

        $claims = $this->service->verify($token, MagicLinkTokenService::SCOPE_INTERVIEW);

        self::assertSame('person-42', $claims->subject);
        self::assertSame(MagicLinkTokenService::SCOPE_INTERVIEW, $claims->scope);
        self::assertGreaterThan(new \DateTimeImmutable('+6 days'), $claims->expiresAt);
        self::assertNotSame('', $claims->tokenId);
    }

    public function testExpiredTokenIsRejected(): void
    {
        $ttl = new \DateInterval('PT1S');
        $ttl->invert = 1; // already in the past

        $token = $this->service->issue('person-42', MagicLinkTokenService::SCOPE_INTERVIEW, $ttl);

        $this->expectException(InvalidMagicLinkException::class);
        $this->expectExceptionMessage('Expired');
        $this->service->verify($token, MagicLinkTokenService::SCOPE_INTERVIEW);
    }

    public function testTamperedTokenIsRejected(): void
    {
        $token = $this->service->issue('person-42', MagicLinkTokenService::SCOPE_INTERVIEW, new \DateInterval('P7D'));
        [$payload, $signature] = explode('.', $token);

        $claims = json_decode((string) base64_decode(strtr($payload, '-_', '+/')), true);
        $claims['sub'] = 'person-66';
        $forgedPayload = rtrim(strtr(base64_encode((string) json_encode($claims)), '+/', '-_'), '=');

        $this->expectException(InvalidMagicLinkException::class);
        $this->expectExceptionMessage('signature');
        $this->service->verify($forgedPayload.'.'.$signature, MagicLinkTokenService::SCOPE_INTERVIEW);
    }

    public function testWrongScopeIsRejected(): void
    {
        $token = $this->service->issue('person-42', MagicLinkTokenService::SCOPE_INTERVIEW, new \DateInterval('P7D'));

        $this->expectException(InvalidMagicLinkException::class);
        $this->expectExceptionMessage('scope');
        $this->service->verify($token, MagicLinkTokenService::SCOPE_PERSONAL_SPACE);
    }

    public function testTokenSignedWithAnotherSecretIsRejected(): void
    {
        $other = new MagicLinkTokenService('another-secret');
        $token = $other->issue('person-42', MagicLinkTokenService::SCOPE_INTERVIEW, new \DateInterval('P7D'));

        $this->expectException(InvalidMagicLinkException::class);
        $this->expectExceptionMessage('signature');
        $this->service->verify($token, MagicLinkTokenService::SCOPE_INTERVIEW);
    }
}
