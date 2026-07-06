<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Module\Security\StudizzAuth\InvalidJwtException;
use App\Module\Security\StudizzAuth\JwtDecoder;
use App\Module\Security\StudizzAuth\StubStudizzAuthClient;
use PHPUnit\Framework\TestCase;

final class JwtDecoderTest extends TestCase
{
    private JwtDecoder $decoder;

    protected function setUp(): void
    {
        $this->decoder = new JwtDecoder();
    }

    public function testDecodesAValidJwt(): void
    {
        $jwt = StubStudizzAuthClient::buildJwt('sophie@ecole-demo.fr', ['ROLE_USER', 'ROLE_ADMIN'], 3600);

        $user = $this->decoder->decode($jwt);

        self::assertSame('sophie@ecole-demo.fr', $user->getUserIdentifier());
        self::assertSame(['ROLE_USER', 'ROLE_ADMIN'], $user->getRoles());
        self::assertSame($jwt, $user->getToken());
        self::assertGreaterThan(new \DateTimeImmutable(), $user->getExpiresAt());
    }

    public function testExpiredJwtIsRejected(): void
    {
        $jwt = StubStudizzAuthClient::buildJwt('sophie@ecole-demo.fr', ['ROLE_USER'], -10);

        $this->expectException(InvalidJwtException::class);
        $this->expectExceptionMessage('Expired');
        $this->decoder->decode($jwt);
    }

    public function testMalformedJwtIsRejected(): void
    {
        $this->expectException(InvalidJwtException::class);
        $this->decoder->decode('not-a-jwt');
    }

    public function testJwtWithoutEmailIsRejected(): void
    {
        $encode = static fn (array $d): string => rtrim(strtr(base64_encode(json_encode($d, \JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
        $jwt = $encode(['alg' => 'none']).'.'.$encode(['exp' => time() + 60]).'.x';

        $this->expectException(InvalidJwtException::class);
        $this->expectExceptionMessage('Incomplete');
        $this->decoder->decode($jwt);
    }
}
