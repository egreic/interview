<?php

declare(strict_types=1);

namespace App\Module\Voice\Adapter;

final class StubTtsClient implements TtsClientInterface
{
    public function synthesize(string $text, string $voice, string $language = 'fr'): string
    {
        return 'stub://tts/'.md5($voice.'|'.$text);
    }
}
