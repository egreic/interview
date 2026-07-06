<?php

declare(strict_types=1);

namespace App\Module\SchoolContext\Document;

use App\Module\Tenancy\TenantOwnedInterface;
use App\Module\Tenancy\TenantOwnedTrait;
use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;

/**
 * A versioned school-context sheet received from the external collector,
 * pending admin validation before any consumer may use it.
 */
#[ODM\Document(collection: 'school_context_sheets')]
class SchoolContextSheet implements TenantOwnedInterface
{
    use TenantOwnedTrait;

    public const STATUS_PENDING_VALIDATION = 'pending_validation';
    public const STATUS_VALIDATED = 'validated';
    public const STATUS_REJECTED = 'rejected';

    #[ODM\Id]
    private ?string $id = null;

    /** Version string from the exchange contract (referenced by interview records). */
    #[ODM\Field(type: 'string')]
    private string $version;

    #[ODM\Field(type: 'string')]
    private string $status = self::STATUS_PENDING_VALIDATION;

    /** Raw payload following the §2.2 exchange contract. */
    #[ODM\Field(type: 'hash')]
    private array $payload;

    #[ODM\Field(type: 'date_immutable')]
    private \DateTimeImmutable $receivedAt;

    public function __construct(string $version, array $payload)
    {
        $this->version = $version;
        $this->payload = $payload;
        $this->receivedAt = new \DateTimeImmutable();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getPayload(): array
    {
        return $this->payload;
    }

    public function getReceivedAt(): \DateTimeImmutable
    {
        return $this->receivedAt;
    }
}
