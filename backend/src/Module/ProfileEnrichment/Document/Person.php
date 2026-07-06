<?php

declare(strict_types=1);

namespace App\Module\ProfileEnrichment\Document;

use App\Module\Tenancy\TenantOwnedInterface;
use App\Module\Tenancy\TenantOwnedTrait;
use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;

#[ODM\Document(collection: 'persons')]
class Person implements TenantOwnedInterface
{
    use TenantOwnedTrait;

    #[ODM\Id]
    private ?string $id = null;

    #[ODM\Field(type: 'string')]
    private string $firstName;

    #[ODM\Field(type: 'string')]
    private string $lastName;

    #[ODM\Field(type: 'string')]
    #[ODM\Index]
    private string $email;

    #[ODM\Field(type: 'int', nullable: true)]
    private ?int $graduationYear;

    #[ODM\Field(type: 'string', nullable: true)]
    private ?string $photoUrl;

    #[ODM\Field(type: 'string', nullable: true)]
    private ?string $programId;

    #[ODM\Field(type: 'date_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct(string $firstName, string $lastName, string $email, ?int $graduationYear = null)
    {
        $this->firstName = $firstName;
        $this->lastName = $lastName;
        $this->email = $email;
        $this->graduationYear = $graduationYear;
        $this->photoUrl = null;
        $this->programId = null;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getGraduationYear(): ?int
    {
        return $this->graduationYear;
    }

    public function getPhotoUrl(): ?string
    {
        return $this->photoUrl;
    }

    public function getProgramId(): ?string
    {
        return $this->programId;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
