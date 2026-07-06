<?php

declare(strict_types=1);

namespace App\Module\LivingProfile\Document;

use App\Module\Tenancy\TenantOwnedInterface;
use App\Module\Tenancy\TenantOwnedTrait;
use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;

#[ODM\Document(collection: 'experiences')]
class Experience implements TenantOwnedInterface
{
    use TenantOwnedTrait;

    public const TYPE_EDUCATION = 'formation';
    public const TYPE_INTERNSHIP = 'stage';
    public const TYPE_JOB = 'emploi';
    public const TYPE_PROJECT = 'projet';
    public const TYPE_AFFILIATION = 'affiliation';

    public const SOURCE_LINKEDIN = 'linkedin';
    public const SOURCE_INTERVIEW = 'interview';

    public const STATUS_TO_CONFIRM = 'a_confirmer';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_EDITED = 'edited';
    public const STATUS_REMOVED = 'removed';

    #[ODM\Id]
    private ?string $id = null;

    #[ODM\Field(type: 'string')]
    #[ODM\Index]
    private string $personId;

    #[ODM\Field(type: 'string')]
    private string $type;

    #[ODM\Field(type: 'string')]
    private string $title;

    #[ODM\Field(type: 'string', nullable: true)]
    private ?string $organization;

    /** @var array<string, ?string> {start, end} as YYYY-MM strings */
    #[ODM\Field(type: 'hash')]
    private array $period;

    #[ODM\Field(type: 'string')]
    private string $source;

    #[ODM\Field(type: 'string')]
    private string $status = self::STATUS_TO_CONFIRM;

    #[ODM\Field(type: 'float')]
    private float $confidence;

    public function __construct(
        string $personId,
        string $type,
        string $title,
        ?string $organization,
        array $period,
        string $source,
        float $confidence = 1.0,
    ) {
        $this->personId = $personId;
        $this->type = $type;
        $this->title = $title;
        $this->organization = $organization;
        $this->period = $period;
        $this->source = $source;
        $this->confidence = $confidence;
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getPersonId(): string
    {
        return $this->personId;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getOrganization(): ?string
    {
        return $this->organization;
    }

    /** @return array<string, ?string> */
    public function getPeriod(): array
    {
        return $this->period;
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getConfidence(): float
    {
        return $this->confidence;
    }
}
