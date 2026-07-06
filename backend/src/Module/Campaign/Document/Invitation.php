<?php

declare(strict_types=1);

namespace App\Module\Campaign\Document;

use App\Module\Tenancy\TenantOwnedInterface;
use App\Module\Tenancy\TenantOwnedTrait;
use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;

#[ODM\Document(collection: 'invitations')]
class Invitation implements TenantOwnedInterface
{
    use TenantOwnedTrait;

    public const STATUS_PENDING = 'pending';
    public const STATUS_SENT = 'sent';
    public const STATUS_OPENED = 'opened';
    public const STATUS_STARTED = 'started';
    public const STATUS_COMPLETED = 'completed';

    #[ODM\Id]
    private ?string $id = null;

    #[ODM\Field(type: 'string')]
    #[ODM\Index]
    private string $campaignId;

    #[ODM\Field(type: 'string')]
    private string $email;

    /** Magic-link token identifier (the signed token itself is never stored). */
    #[ODM\Field(type: 'string', nullable: true)]
    #[ODM\Index]
    private ?string $tokenId = null;

    #[ODM\Field(type: 'string')]
    private string $status = self::STATUS_PENDING;

    public function __construct(string $campaignId, string $email)
    {
        $this->campaignId = $campaignId;
        $this->email = $email;
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getCampaignId(): string
    {
        return $this->campaignId;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getTokenId(): ?string
    {
        return $this->tokenId;
    }

    public function getStatus(): string
    {
        return $this->status;
    }
}
