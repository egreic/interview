<?php

declare(strict_types=1);

namespace App\Module\Notification\Adapter;

/**
 * Adapter to the studizz-api-mailer gateway (§0.4 decision): POST /mail and
 * POST /sms/send, base URL from STUDIZZ_API_MAILER_URL. A 200 HTTP status is
 * not enough — always read `code` in the response body for the real outcome.
 */
interface MailerClientInterface
{
    /** @param array<string, mixed> $payload @return array<string, mixed> gateway response body */
    public function sendMail(array $payload): array;

    /** @param array<string, mixed> $payload @return array<string, mixed> gateway response body */
    public function sendSms(array $payload): array;
}
