<?php

declare(strict_types=1);

namespace App\Module\ProfileEnrichment\Adapter;

/** Dev/test stub — deterministic fixture candidates, no network. */
final class StubLinkedInClient implements LinkedInClientInterface
{
    public function search(string $lastName, string $firstName, array $hints = []): array
    {
        return [
            [
                'profile_id' => 'stub-1',
                'name' => $firstName.' '.$lastName,
                'headline' => 'Chef de projet · Lyon',
                'photo_url' => null,
            ],
        ];
    }

    public function fetch(string $profileId): array
    {
        return [
            'profile_id' => $profileId,
            'experiences' => [
                ['title' => 'Chef de projet', 'organization' => 'Acme', 'start' => '2021-09', 'end' => null],
                ['title' => 'Stagiaire marketing', 'organization' => 'Retail Co', 'start' => '2020-04', 'end' => '2020-08'],
            ],
        ];
    }
}
