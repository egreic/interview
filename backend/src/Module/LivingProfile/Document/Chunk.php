<?php

declare(strict_types=1);

namespace App\Module\LivingProfile\Document;

use App\Module\Tenancy\TenantOwnedInterface;
use App\Module\Tenancy\TenantOwnedTrait;
use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;

/**
 * Living-profile chunk (§2.10). Versioned transformer output — a re-run
 * produces new versions, never overwrites (invariant #8). Visibility is
 * enforced all the way to the prompt: exclusions are ALSO injected as
 * explicit prompt interdictions (invariant #6).
 */
#[ODM\Document(collection: 'chunks')]
class Chunk implements TenantOwnedInterface
{
    use TenantOwnedTrait;

    public const TYPE_FACT = 'fait';
    public const TYPE_ANECDOTE = 'anecdote';
    public const TYPE_OPINION = 'opinion';
    public const TYPE_ADVICE = 'conseil';
    public const TYPE_SKILL = 'competence';

    public const SOURCE_INTERVIEW = 'interview';
    public const SOURCE_SCHOOL = 'ecole';
    public const SOURCE_PUBLIC = 'public';

    public const VISIBILITY_PUBLIC = 'public';
    public const VISIBILITY_SCHOOL_ONLY = 'reserve_ecole';
    public const VISIBILITY_PRIVATE = 'prive';

    #[ODM\Id]
    private ?string $id = null;

    #[ODM\Field(type: 'string')]
    #[ODM\Index]
    private string $personId;

    #[ODM\Field(type: 'string')]
    private string $text;

    #[ODM\Field(type: 'string')]
    private string $type;

    #[ODM\Field(type: 'string', nullable: true)]
    private ?string $period;

    #[ODM\Field(type: 'string', nullable: true)]
    private ?string $experienceId;

    #[ODM\Field(type: 'string')]
    private string $theme;

    #[ODM\Field(type: 'string')]
    private string $source;

    #[ODM\Field(type: 'string')]
    #[ODM\Index]
    private string $visibility;

    #[ODM\Field(type: 'bool')]
    private bool $verbatim;

    #[ODM\Field(type: 'float')]
    private float $confidence;

    #[ODM\Field(type: 'int')]
    private int $version;

    #[ODM\Field(type: 'string')]
    private string $recordId;

    public function __construct(
        string $personId,
        string $text,
        string $type,
        string $theme,
        string $source,
        string $visibility,
        bool $verbatim,
        float $confidence,
        int $version,
        string $recordId,
        ?string $period = null,
        ?string $experienceId = null,
    ) {
        $this->personId = $personId;
        $this->text = $text;
        $this->type = $type;
        $this->theme = $theme;
        $this->source = $source;
        $this->visibility = $visibility;
        $this->verbatim = $verbatim;
        $this->confidence = $confidence;
        $this->version = $version;
        $this->recordId = $recordId;
        $this->period = $period;
        $this->experienceId = $experienceId;
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getPersonId(): string
    {
        return $this->personId;
    }

    public function getText(): string
    {
        return $this->text;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getPeriod(): ?string
    {
        return $this->period;
    }

    public function getExperienceId(): ?string
    {
        return $this->experienceId;
    }

    public function getTheme(): string
    {
        return $this->theme;
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function getVisibility(): string
    {
        return $this->visibility;
    }

    public function isVerbatim(): bool
    {
        return $this->verbatim;
    }

    public function getConfidence(): float
    {
        return $this->confidence;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function getRecordId(): string
    {
        return $this->recordId;
    }
}
