<?php

declare(strict_types=1);

namespace App\Module\AvatarWorkshop\Document;

use App\Module\Tenancy\TenantOwnedInterface;
use App\Module\Tenancy\TenantOwnedTrait;
use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;

/**
 * Avatar (§2.12). Its prompt is COMPILED in layers (base socle version +
 * use case + school + person + few-shot from the person's own verbatims) —
 * nobody hand-writes an avatar prompt. The person never sees the prompt.
 */
#[ODM\Document(collection: 'avatars')]
class Avatar implements TenantOwnedInterface
{
    use TenantOwnedTrait;

    public const STATUS_DRAFT = 'brouillon';
    public const STATUS_SELF_TEST = 'test_perso';
    public const STATUS_TEST_BENCH = 'banc_essai';
    public const STATUS_SUBMITTED = 'soumis';
    public const STATUS_VALIDATED = 'valide';
    public const STATUS_ACTIVE = 'actif';
    public const STATUS_FEATURED = 'mis_en_avant';
    public const STATUS_NOT_PUBLIC = 'non_public';
    public const STATUS_REFUSED = 'refuse';
    public const STATUS_MODIFIED_OPPOSITION_WINDOW = 'modifie_fenetre_opposition';

    #[ODM\Id]
    private ?string $id = null;

    #[ODM\Field(type: 'string')]
    #[ODM\Index]
    private string $personId;

    #[ODM\Field(type: 'string')]
    private string $status = self::STATUS_DRAFT;

    /** Version of the generic prompt socle this avatar was compiled with. */
    #[ODM\Field(type: 'string', nullable: true)]
    private ?string $socleVersion = null;

    /** @var array<string, mixed> compiled layer references (use case, school, person, few-shot) */
    #[ODM\Field(type: 'hash')]
    private array $promptLayers = [];

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

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getSocleVersion(): ?string
    {
        return $this->socleVersion;
    }

    /** @return array<string, mixed> */
    public function getPromptLayers(): array
    {
        return $this->promptLayers;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
