<?php

declare(strict_types=1);

namespace App\Module\AvatarWorkshop\Document;

use App\Module\Tenancy\TenantOwnedInterface;
use App\Module\Tenancy\TenantOwnedTrait;
use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;

/**
 * Journal entry of a person's natural-language modification to their avatar.
 * Tricolor invariant (#7): every patch is classified green/orange/red and no
 * patch bypasses that classification; the person may ADD interdictions, never
 * remove one; socle and school layers are out of reach.
 */
#[ODM\Document(collection: 'persona_patches')]
class PersonaPatch implements TenantOwnedInterface
{
    use TenantOwnedTrait;

    public const CLASS_GREEN = 'vert';
    public const CLASS_ORANGE = 'orange';
    public const CLASS_RED = 'rouge';

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPLIED = 'applied';
    public const STATUS_OPPOSITION_WINDOW = 'fenetre_opposition';
    public const STATUS_BLOCKED_SCHOOL_REVIEW = 'validation_ecole';
    public const STATUS_REJECTED = 'rejected';

    #[ODM\Id]
    private ?string $id = null;

    #[ODM\Field(type: 'string')]
    #[ODM\Index]
    private string $avatarId;

    /** The person's instruction, in natural language (the prompt is never shown). */
    #[ODM\Field(type: 'string')]
    private string $instruction;

    #[ODM\Field(type: 'string')]
    private string $classification;

    #[ODM\Field(type: 'string')]
    private string $status = self::STATUS_PENDING;

    #[ODM\Field(type: 'date_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct(string $avatarId, string $instruction, string $classification)
    {
        $this->avatarId = $avatarId;
        $this->instruction = $instruction;
        $this->classification = $classification;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getAvatarId(): string
    {
        return $this->avatarId;
    }

    public function getInstruction(): string
    {
        return $this->instruction;
    }

    public function getClassification(): string
    {
        return $this->classification;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
