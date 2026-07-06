<?php

declare(strict_types=1);

namespace App\Module\CanonicalRecord\Document;

use App\Module\Tenancy\TenantOwnedInterface;
use App\Module\Tenancy\TenantOwnedTrait;
use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;

/**
 * Author correction — the ONLY way a canonical record evolves: a posterior
 * annotation layer with top priority for every transformer, never a mutation.
 */
#[ODM\Document(collection: 'author_corrections')]
class AuthorCorrection implements TenantOwnedInterface
{
    use TenantOwnedTrait;

    #[ODM\Id]
    private ?string $id = null;

    #[ODM\Field(type: 'string')]
    #[ODM\Index]
    private string $recordId;

    /** What is being corrected (e.g. a turn reference or a field path). */
    #[ODM\Field(type: 'string')]
    private string $target;

    #[ODM\Field(type: 'string')]
    private string $correction;

    #[ODM\Field(type: 'date_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct(string $recordId, string $target, string $correction)
    {
        $this->recordId = $recordId;
        $this->target = $target;
        $this->correction = $correction;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getRecordId(): string
    {
        return $this->recordId;
    }

    public function getTarget(): string
    {
        return $this->target;
    }

    public function getCorrection(): string
    {
        return $this->correction;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
