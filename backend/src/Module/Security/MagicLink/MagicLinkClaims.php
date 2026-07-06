<?php

declare(strict_types=1);

namespace App\Module\Security\MagicLink;

final readonly class MagicLinkClaims
{
    public function __construct(
        public string $subject,
        public string $scope,
        public \DateTimeImmutable $expiresAt,
        public string $tokenId,
    ) {
    }
}
