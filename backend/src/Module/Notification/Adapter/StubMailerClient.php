<?php

declare(strict_types=1);

namespace App\Module\Notification\Adapter;

/** Dev/test stub — records payloads in memory, sends nothing. */
final class StubMailerClient implements MailerClientInterface
{
    /** @var list<array<string, mixed>> */
    public array $sentMails = [];

    /** @var list<array<string, mixed>> */
    public array $sentSms = [];

    public function sendMail(array $payload): array
    {
        $this->sentMails[] = $payload;

        return ['code' => 200, 'message' => 'ok (stub)'];
    }

    public function sendSms(array $payload): array
    {
        $this->sentSms[] = $payload;

        return ['code' => 200, 'message' => 'ok (stub)'];
    }
}
