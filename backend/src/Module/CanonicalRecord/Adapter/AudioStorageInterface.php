<?php

declare(strict_types=1);

namespace App\Module\CanonicalRecord\Adapter;

/**
 * Object storage adapter for session audio (§0.5 hypothesis: S3-compatible).
 * Retention is tenant-configurable (default: audio purged at 12 months,
 * transcript kept).
 */
interface AudioStorageInterface
{
    /** @param resource|string $content @return string storage key */
    public function store(string $key, mixed $content): string;

    public function url(string $key): string;

    public function delete(string $key): void;
}
