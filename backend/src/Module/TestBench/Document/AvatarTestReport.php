<?php

declare(strict_types=1);

namespace App\Module\TestBench\Document;

use App\Module\Tenancy\TenantOwnedInterface;
use App\Module\Tenancy\TenantOwnedTrait;
use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;

#[ODM\Document(collection: 'avatar_test_reports')]
class AvatarTestReport implements TenantOwnedInterface
{
    use TenantOwnedTrait;

    public const VERDICT_POSITIVE = 'positif';
    public const VERDICT_DOUBT = 'doute';
    public const VERDICT_NEGATIVE = 'negatif';

    #[ODM\Id]
    private ?string $id = null;

    #[ODM\Field(type: 'string')]
    #[ODM\Index]
    private string $avatarId;

    #[ODM\Field(type: 'string')]
    private string $scenarioName;

    #[ODM\Field(type: 'string')]
    private string $verdict;

    /** @var list<array<string, mixed>> orchestrator transcript */
    #[ODM\Field(type: 'collection')]
    private array $transcript;

    /** @var list<array<string, mixed>> per-message judge notes */
    #[ODM\Field(type: 'collection')]
    private array $perMessage;

    #[ODM\Field(type: 'string', nullable: true)]
    private ?string $notes;

    #[ODM\Field(type: 'date_immutable')]
    private \DateTimeImmutable $ranAt;

    public function __construct(
        string $avatarId,
        string $scenarioName,
        string $verdict,
        array $transcript,
        array $perMessage,
        ?string $notes = null,
    ) {
        $this->avatarId = $avatarId;
        $this->scenarioName = $scenarioName;
        $this->verdict = $verdict;
        $this->transcript = $transcript;
        $this->perMessage = $perMessage;
        $this->notes = $notes;
        $this->ranAt = new \DateTimeImmutable();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getAvatarId(): string
    {
        return $this->avatarId;
    }

    public function getScenarioName(): string
    {
        return $this->scenarioName;
    }

    public function getVerdict(): string
    {
        return $this->verdict;
    }

    /** @return list<array<string, mixed>> */
    public function getTranscript(): array
    {
        return $this->transcript;
    }

    /** @return list<array<string, mixed>> */
    public function getPerMessage(): array
    {
        return $this->perMessage;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function getRanAt(): \DateTimeImmutable
    {
        return $this->ranAt;
    }
}
