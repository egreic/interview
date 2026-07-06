<?php

declare(strict_types=1);

namespace App\Module\Security\StudizzAuth;

/**
 * Client of the central studizz-auth service — the single source of truth
 * for Studio admins and technical accounts (arbitration #3). This project
 * never stores passwords: it forwards credentials and trusts the returned
 * JWT. Every request carries the project key in `x-api-key`.
 */
interface StudizzAuthClientInterface
{
    /** @return array{token: string, refresh_token: string} */
    public function loginCheck(string $email, #[\SensitiveParameter] string $password): array;

    /** @return array{token: string, refresh_token: string} */
    public function refreshToken(string $refreshToken): array;
}
