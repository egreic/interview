<?php

declare(strict_types=1);

namespace App\Module\ProgramReferential\Contract;

/**
 * Brick contract — autocompletion with historical aliases, free proposal by
 * alumni, school-side reconciliation. Implemented at J3.
 */
interface ProgramReferentialServiceInterface
{
    /** @return list<array<string, mixed>> matches, sorted by the entered graduation year */
    public function autocomplete(string $query, ?int $graduationYear = null): array;

    /** Free-text proposal, immediately usable by its author. Returns the program id. */
    public function proposeByAlumni(string $name): string;
}
