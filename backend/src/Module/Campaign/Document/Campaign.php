<?php

declare(strict_types=1);

namespace App\Module\Campaign\Document;

use App\Module\Tenancy\TenantOwnedInterface;
use App\Module\Tenancy\TenantOwnedTrait;
use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;

#[ODM\Document(collection: 'campaigns')]
class Campaign implements TenantOwnedInterface
{
    use TenantOwnedTrait;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_RUNNING = 'running';
    public const STATUS_CLOSED = 'closed';

    #[ODM\Id]
    private ?string $id = null;

    #[ODM\Field(type: 'string')]
    private string $name;

    #[ODM\Field(type: 'string')]
    private string $status = self::STATUS_DRAFT;

    /** Frozen template reference. */
    #[ODM\Field(type: 'string', nullable: true)]
    private ?string $templateId = null;

    #[ODM\Field(type: 'int', nullable: true)]
    private ?int $templateVersion = null;

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getTemplateId(): ?string
    {
        return $this->templateId;
    }

    public function getTemplateVersion(): ?int
    {
        return $this->templateVersion;
    }
}
