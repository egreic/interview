<?php

declare(strict_types=1);

namespace App\Module\CanonicalRecord\Contract;

/**
 * Brick contract — records are written once at session close and read by
 * transformers (with author corrections applied at top priority). J2.
 */
interface CanonicalRecordServiceInterface
{
    /** @return array<string, mixed> the record with author corrections applied */
    public function getEffectiveRecord(string $recordId): array;

    public function addAuthorCorrection(string $recordId, string $target, string $correction): void;
}
