<?php

declare(strict_types=1);

namespace App\Module\SchoolContext\Adapter;

/** Dev fixture following the §2.2 exchange contract shape. */
final class StubSchoolContextProvider implements SchoolContextProviderInterface
{
    public function fetchLatest(string $tenantSlug): array
    {
        return [
            'version' => 'stub-1',
            'horodatage' => '2026-01-01T00:00:00+00:00',
            'ecole' => [
                'nom' => 'École Démo',
                'recit' => 'École de commerce fictive utilisée pour le développement.',
                'differenciateurs' => ['alternance forte', 'campus à taille humaine'],
            ],
            'formations' => [
                ['nom' => 'Bachelor Marketing', 'diplome' => 'Bachelor', 'rythme' => 'alternance', 'statut' => 'active', 'alias' => []],
                ['nom' => 'BTS Communication', 'diplome' => 'BTS', 'rythme' => 'initial', 'statut' => 'active', 'alias' => ['BTS Communication des entreprises']],
            ],
            'vie_etudiante' => [],
            'admissions' => [],
            'chiffres' => [],
            'vocabulaire_maison' => [
                ['terme' => 'Le Hub', 'definition' => 'Espace de coworking du campus.'],
            ],
            'regles_communication' => ['arguments' => [], 'interdits' => [], 'questions_pieges' => []],
        ];
    }
}
