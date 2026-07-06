<?php

declare(strict_types=1);

namespace App\Module\Voice\Adapter;

/**
 * Text-to-speech adapter (§0.3 — provider to plug in). Used for the tone
 * choice samples (E09) and tests; streaming TTS lives in the voice gateway.
 */
interface TtsClientInterface
{
    /** @return string URL of the synthesized audio */
    public function synthesize(string $text, string $voice, string $language = 'fr'): string;
}
