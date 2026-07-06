<?php

declare(strict_types=1);

namespace App\Module\CanonicalRecord\Adapter;

/** Dev/test stub — in-memory, no persistence. */
final class StubAudioStorage implements AudioStorageInterface
{
    /** @var array<string, string> */
    private array $objects = [];

    public function store(string $key, mixed $content): string
    {
        $this->objects[$key] = \is_resource($content) ? (stream_get_contents($content) ?: '') : (string) $content;

        return $key;
    }

    public function url(string $key): string
    {
        return 'stub://audio/'.$key;
    }

    public function delete(string $key): void
    {
        unset($this->objects[$key]);
    }
}
