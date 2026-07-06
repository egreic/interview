<?php

declare(strict_types=1);

namespace App\Module\Security\StudizzAuth;

use Symfony\Component\Security\Core\User\UserInterface;

/** Studio admin or technical account, materialized from a studizz-auth JWT. */
final class AdminUser implements UserInterface
{
    /** @param list<string> $roles */
    public function __construct(
        private readonly string $email,
        private readonly array $roles,
        private readonly string $uuid,
        private readonly string $username,
        private readonly string $token,
        private readonly \DateTimeImmutable $expiresAt,
    ) {
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    public function getRoles(): array
    {
        return $this->roles;
    }

    public function getUuid(): string
    {
        return $this->uuid;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function getExpiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function eraseCredentials(): void
    {
    }
}
