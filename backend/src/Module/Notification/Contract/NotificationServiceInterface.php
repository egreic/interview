<?php

declare(strict_types=1);

namespace App\Module\Notification\Contract;

/**
 * Transverse contract — invitation, reminder, postponement, review-pending,
 * "your avatar is live", 72h opposition, weekly school digest. Sender is the
 * platform, reply-to is the school. Implemented from J3 on.
 */
interface NotificationServiceInterface
{
    /** @param array<string, mixed> $variables template variables */
    public function sendEmail(string $to, string $template, array $variables = []): void;
}
