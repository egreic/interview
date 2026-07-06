<?php

declare(strict_types=1);

namespace App\Module\InterviewTemplate\Document;

use App\Module\Tenancy\TenantOwnedInterface;
use App\Module\Tenancy\TenantOwnedTrait;
use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;

/**
 * Editorial strategy — one per tenant, versioned. Guard rails (§2.4): weights
 * THEMES, never answers; the adaptive cursor wins over the strategy.
 */
#[ODM\Document(collection: 'editorial_strategies')]
class EditorialStrategy implements TenantOwnedInterface
{
    use TenantOwnedTrait;

    #[ODM\Id]
    private ?string $id = null;

    #[ODM\Field(type: 'int')]
    private int $version = 1;

    /** @var list<array<string, mixed>> axes: {titre, justification, source: site|personnel|emergent, poids, statut} */
    #[ODM\Field(type: 'collection')]
    private array $axes = [];

    #[ODM\Field(type: 'string', nullable: true)]
    private ?string $schoolNotes = null;

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    /** @return list<array<string, mixed>> */
    public function getAxes(): array
    {
        return $this->axes;
    }

    public function getSchoolNotes(): ?string
    {
        return $this->schoolNotes;
    }
}
