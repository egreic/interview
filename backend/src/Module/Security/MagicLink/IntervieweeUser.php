<?php

declare(strict_types=1);

namespace App\Module\Security\MagicLink;

use Symfony\Component\Security\Core\User\UserInterface;

final class IntervieweeUser implements UserInterface
{
    public function __construct(
        private readonly string $subject,
        private readonly string $scope,
    ) {
    }

    public function getUserIdentifier(): string
    {
        return $this->subject;
    }

    public function getScope(): string
    {
        return $this->scope;
    }

    public function getRoles(): array
    {
        return ['ROLE_INTERVIEWEE'];
    }

    public function eraseCredentials(): void
    {
    }
}
