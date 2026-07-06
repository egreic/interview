<?php

declare(strict_types=1);

namespace App\Module\Security\MagicLink;

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
 * Stateless authenticator for interviewee requests carrying a magic-link
 * token in the X-Magic-Token header.
 */
final class MagicLinkAuthenticator extends AbstractAuthenticator
{
    public const HEADER = 'X-Magic-Token';

    public function __construct(private readonly MagicLinkTokenService $tokens)
    {
    }

    public function supports(Request $request): ?bool
    {
        return $request->headers->has(self::HEADER);
    }

    public function authenticate(Request $request): Passport
    {
        $token = (string) $request->headers->get(self::HEADER, '');

        try {
            $claims = $this->tokens->verify($token, MagicLinkTokenService::SCOPE_INTERVIEW);
        } catch (InvalidMagicLinkException $e) {
            throw new CustomUserMessageAuthenticationException($e->getMessage());
        }

        return new SelfValidatingPassport(new UserBadge(
            $claims->subject,
            static fn (string $subject): IntervieweeUser => new IntervieweeUser($subject, $claims->scope),
        ));
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return new JsonResponse(
            ['code' => Response::HTTP_UNAUTHORIZED, 'message' => 'Invalid or expired magic link.'],
            Response::HTTP_UNAUTHORIZED,
        );
    }
}
