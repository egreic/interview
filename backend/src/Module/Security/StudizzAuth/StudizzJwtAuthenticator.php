<?php

declare(strict_types=1);

namespace App\Module\Security\StudizzAuth;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

/**
 * Stateless Bearer authenticator for Studio admins and technical accounts —
 * decodes the studizz-auth JWT locally.
 */
final class StudizzJwtAuthenticator extends AbstractAuthenticator
{
    public function __construct(private readonly JwtDecoder $decoder)
    {
    }

    public function supports(Request $request): ?bool
    {
        $header = (string) $request->headers->get('Authorization', '');

        return str_starts_with($header, 'Bearer ') && 2 === substr_count($header, '.');
    }

    public function authenticate(Request $request): Passport
    {
        $jwt = substr((string) $request->headers->get('Authorization'), 7);

        try {
            $user = $this->decoder->decode($jwt);
        } catch (InvalidJwtException $e) {
            throw new CustomUserMessageAuthenticationException($e->getMessage());
        }

        return new SelfValidatingPassport(new UserBadge(
            $user->getUserIdentifier(),
            static fn (): AdminUser => $user,
        ));
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return new JsonResponse(
            ['code' => Response::HTTP_UNAUTHORIZED, 'message' => 'Invalid or expired token.'],
            Response::HTTP_UNAUTHORIZED,
        );
    }
}
