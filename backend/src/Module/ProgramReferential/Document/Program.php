<?php

declare(strict_types=1);

namespace App\Module\ProgramReferential\Document;

use App\Module\Tenancy\TenantOwnedInterface;
use App\Module\Tenancy\TenantOwnedTrait;
use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;

/**
 * A program ("formation") in the living referential — three layers: official,
 * proposed by alumni, reconciled (historical renames become aliases).
 */
#[ODM\Document(collection: 'programs')]
class Program implements TenantOwnedInterface
{
    use TenantOwnedTrait;

    public const STATUS_OFFICIAL = 'official';
    public const STATUS_PROPOSED = 'proposed';
    public const STATUS_RECONCILED = 'reconciled';

    #[ODM\Id]
    private ?string $id = null;

    #[ODM\Field(type: 'string')]
    private string $name;

    #[ODM\Field(type: 'string', nullable: true)]
    private ?string $degree;

    #[ODM\Field(type: 'string')]
    private string $status;

    /** @var list<string> historical names feeding autocompletion */
    #[ODM\Field(type: 'collection')]
    private array $aliases = [];

    #[ODM\Field(type: 'int', nullable: true)]
    private ?int $validFromYear = null;

    #[ODM\Field(type: 'int', nullable: true)]
    private ?int $validUntilYear = null;

    public function __construct(string $name, ?string $degree = null, string $status = self::STATUS_OFFICIAL)
    {
        $this->name = $name;
        $this->degree = $degree;
        $this->status = $status;
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDegree(): ?string
    {
        return $this->degree;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    /** @return list<string> */
    public function getAliases(): array
    {
        return $this->aliases;
    }

    public function getValidFromYear(): ?int
    {
        return $this->validFromYear;
    }

    public function getValidUntilYear(): ?int
    {
        return $this->validUntilYear;
    }
}
