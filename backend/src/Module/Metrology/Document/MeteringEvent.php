<?php

declare(strict_types=1);

namespace App\Module\Metrology\Document;

use App\Module\Tenancy\TenantOwnedInterface;
use App\Module\Tenancy\TenantOwnedTrait;
use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;

/**
 * Per-call cost counter (stt_secondes, tts_caracteres, llm_tokens_in/out,
 * linkedin_lookups) aggregated by tenant/campaign/session — feeds quotas and
 * pre-campaign estimates, and prepares phase-2 third-party billing.
 */
#[ODM\Document(collection: 'metering_events')]
class MeteringEvent implements TenantOwnedInterface
{
    use TenantOwnedTrait;

    #[ODM\Id]
    private ?string $id = null;

    #[ODM\Field(type: 'string')]
    #[ODM\Index]
    private string $kind;

    #[ODM\Field(type: 'float')]
    private float $quantity;

    #[ODM\Field(type: 'string', nullable: true)]
    #[ODM\Index]
    private ?string $sessionId;

    #[ODM\Field(type: 'string', nullable: true)]
    #[ODM\Index]
    private ?string $campaignId;

    #[ODM\Field(type: 'date_immutable')]
    private \DateTimeImmutable $occurredAt;

    public function __construct(string $kind, float $quantity, ?string $sessionId = null, ?string $campaignId = null)
    {
        $this->kind = $kind;
        $this->quantity = $quantity;
        $this->sessionId = $sessionId;
        $this->campaignId = $campaignId;
        $this->occurredAt = new \DateTimeImmutable();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getKind(): string
    {
        return $this->kind;
    }

    public function getQuantity(): float
    {
        return $this->quantity;
    }

    public function getSessionId(): ?string
    {
        return $this->sessionId;
    }

    public function getCampaignId(): ?string
    {
        return $this->campaignId;
    }

    public function getOccurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
