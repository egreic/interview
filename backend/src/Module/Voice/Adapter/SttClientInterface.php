<?php

declare(strict_types=1);

namespace App\Module\Voice\Adapter;

/**
 * Speech-to-text adapter (§0.3 — provider to plug in). Streaming lives in the
 * Python voice gateway; this backend-side contract covers batch needs
 * (re-transcription, tests). Proper nouns are unreliable: consumers must keep
 * `a_confirmer` fields, never blind-trust.
 */
interface SttClientInterface
{
    /** @return string the transcript */
    public function transcribe(string $audioUrl, string $language = 'fr'): string;
}
