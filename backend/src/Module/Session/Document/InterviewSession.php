<?php

declare(strict_types=1);

namespace App\Module\Session\Document;

use App\Module\Tenancy\TenantOwnedInterface;
use App\Module\Tenancy\TenantOwnedTrait;
use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;

/**
 * Realtime interview session. State machine (§2.6):
 * creee → profil → consentements → en_cours(theme_i) ⇄ en_pause → validation
 * → close | reportee | abandonnee. Every turn persists before answering
 * (continuous save — a crash never costs more than one turn).
 */
#[ODM\Document(collection: 'interview_sessions')]
class InterviewSession implements TenantOwnedInterface
{
    use TenantOwnedTrait;

    public const STATE_CREATED = 'creee';
    public const STATE_PROFILE = 'profil';
    public const STATE_CONSENTS = 'consentements';
    public const STATE_IN_PROGRESS = 'en_cours';
    public const STATE_PAUSED = 'en_pause';
    public const STATE_VALIDATION = 'validation';
    public const STATE_CLOSED = 'close';
    public const STATE_POSTPONED = 'reportee';
    public const STATE_ABANDONED = 'abandonnee';

    #[ODM\Id]
    private ?string $id = null;

    #[ODM\Field(type: 'string')]
    #[ODM\Index]
    private string $personId;

    #[ODM\Field(type: 'string')]
    private string $state = self::STATE_CREATED;

    #[ODM\Field(type: 'int', nullable: true)]
    private ?int $currentThemeIndex = null;

    /** @var array<string, float> completeness score per theme */
    #[ODM\Field(type: 'hash')]
    private array $completenessScores = [];

    #[ODM\Field(type: 'date_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct(string $personId)
    {
        $this->personId = $personId;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getPersonId(): string
    {
        return $this->personId;
    }

    public function getState(): string
    {
        return $this->state;
    }

    public function getCurrentThemeIndex(): ?int
    {
        return $this->currentThemeIndex;
    }

    /** @return array<string, float> */
    public function getCompletenessScores(): array
    {
        return $this->completenessScores;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
