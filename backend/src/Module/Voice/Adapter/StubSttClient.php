<?php

declare(strict_types=1);

namespace App\Module\Voice\Adapter;

final class StubSttClient implements SttClientInterface
{
    public function transcribe(string $audioUrl, string $language = 'fr'): string
    {
        return '[stub transcript for '.$audioUrl.']';
    }
}
