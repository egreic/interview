<?php

declare(strict_types=1);

namespace App\Module\CanonicalRecord\Document;

use App\Module\Tenancy\TenantOwnedInterface;
use App\Module\Tenancy\TenantOwnedTrait;
use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;

/**
 * IMMUTABLE record of one interview session (architecture invariant #1):
 * built once at session close, never mutated afterwards. Any evolution is an
 * AuthorCorrection annotation or a new transformer output version — this
 * class intentionally exposes no setters.
 */
#[ODM\Document(collection: 'canonical_records')]
class CanonicalRecord implements TenantOwnedInterface
{
    use TenantOwnedTrait;

    #[ODM\Id]
    private ?string $id = null;

    #[ODM\Field(type: 'string')]
    #[ODM\Index(unique: true)]
    private string $sessionId;

    /** @var list<array<string, mixed>> timestamped turns, with S3 audio references */
    #[ODM\Field(type: 'collection')]
    private array $turns;

    /** @var array<string, mixed> QCM answers */
    #[ODM\Field(type: 'hash')]
    private array $qcmAnswers;

    /** @var list<array<string, mixed>> confidentiality markings made during the session */
    #[ODM\Field(type: 'collection')]
    private array $privacyMarkings;

    /** @var list<string> flags (e.g. `sensible`) raised by the engine */
    #[ODM\Field(type: 'collection')]
    private array $flags;

    /** @var array<string, mixed> template id+version, school context version, consents, duration, device */
    #[ODM\Field(type: 'hash')]
    private array $metadata;

    #[ODM\Field(type: 'date_immutable')]
    private \DateTimeImmutable $recordedAt;

    public function __construct(
        string $sessionId,
        array $turns,
        array $qcmAnswers,
        array $privacyMarkings,
        array $flags,
        array $metadata,
    ) {
        $this->sessionId = $sessionId;
        $this->turns = $turns;
        $this->qcmAnswers = $qcmAnswers;
        $this->privacyMarkings = $privacyMarkings;
        $this->flags = $flags;
        $this->metadata = $metadata;
        $this->recordedAt = new \DateTimeImmutable();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getSessionId(): string
    {
        return $this->sessionId;
    }

    /** @return list<array<string, mixed>> */
    public function getTurns(): array
    {
        return $this->turns;
    }

    /** @return array<string, mixed> */
    public function getQcmAnswers(): array
    {
        return $this->qcmAnswers;
    }

    /** @return list<array<string, mixed>> */
    public function getPrivacyMarkings(): array
    {
        return $this->privacyMarkings;
    }

    /** @return list<string> */
    public function getFlags(): array
    {
        return $this->flags;
    }

    /** @return array<string, mixed> */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function getRecordedAt(): \DateTimeImmutable
    {
        return $this->recordedAt;
    }
}
