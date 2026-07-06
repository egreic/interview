<?php

declare(strict_types=1);

namespace App\Module\InterviewTemplate\Document;

use App\Module\Tenancy\TenantOwnedInterface;
use App\Module\Tenancy\TenantOwnedTrait;
use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;

/**
 * Interview template ("trame") — versioned; campaigns reference a frozen
 * version. Themes stay a raw structure until the generator lands (J6).
 */
#[ODM\Document(collection: 'interview_templates')]
class InterviewTemplate implements TenantOwnedInterface
{
    use TenantOwnedTrait;

    #[ODM\Id]
    private ?string $id = null;

    #[ODM\Field(type: 'string')]
    private string $title;

    #[ODM\Field(type: 'int')]
    private int $version = 1;

    #[ODM\Field(type: 'string')]
    private string $useCase;

    #[ODM\Field(type: 'int')]
    private int $targetDurationMinutes;

    /** @var list<array<string, mixed>> ordered themes: {titre, objectif, curseur_conduite, budget_minutes, questions[], qcm[], obligatoire, axes[]} */
    #[ODM\Field(type: 'collection')]
    private array $themes = [];

    public function __construct(string $title, string $useCase, int $targetDurationMinutes = 20)
    {
        $this->title = $title;
        $this->useCase = $useCase;
        $this->targetDurationMinutes = $targetDurationMinutes;
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function getUseCase(): string
    {
        return $this->useCase;
    }

    public function getTargetDurationMinutes(): int
    {
        return $this->targetDurationMinutes;
    }

    /** @return list<array<string, mixed>> */
    public function getThemes(): array
    {
        return $this->themes;
    }
}
