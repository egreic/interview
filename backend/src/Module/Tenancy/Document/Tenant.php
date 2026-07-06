<?php

declare(strict_types=1);

namespace App\Module\Tenancy\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;

/** Platform-level document — deliberately NOT tenant-owned. */
#[ODM\Document(collection: 'tenants')]
class Tenant
{
    #[ODM\Id]
    private ?string $id = null;

    #[ODM\Field(type: 'string')]
    private string $name;

    #[ODM\Field(type: 'string')]
    #[ODM\Index(unique: true)]
    private string $slug;

    /** School accent color, extracted from the school website (design system). */
    #[ODM\Field(type: 'string', nullable: true)]
    private ?string $accentColor;

    #[ODM\Field(type: 'date_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct(string $name, string $slug, ?string $accentColor = null)
    {
        $this->name = $name;
        $this->slug = $slug;
        $this->accentColor = $accentColor;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function getAccentColor(): ?string
    {
        return $this->accentColor;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
