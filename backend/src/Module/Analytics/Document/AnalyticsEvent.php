<?php

declare(strict_types=1);

namespace App\Module\Analytics\Document;

use App\Module\Tenancy\TenantOwnedInterface;
use App\Module\Tenancy\TenantOwnedTrait;
use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;

#[ODM\Document(collection: 'analytics_events')]
class AnalyticsEvent implements TenantOwnedInterface
{
    use TenantOwnedTrait;

    #[ODM\Id]
    private ?string $id = null;

    /** Event name, e.g. `invitation.envoyee`, `interview.demarree`, `avatar.publie`. */
    #[ODM\Field(type: 'string')]
    #[ODM\Index]
    private string $name;

    /** @var array<string, mixed> */
    #[ODM\Field(type: 'hash')]
    private array $payload;

    #[ODM\Field(type: 'date_immutable')]
    #[ODM\Index]
    private \DateTimeImmutable $occurredAt;

    public function __construct(string $name, array $payload = [])
    {
        $this->name = $name;
        $this->payload = $payload;
        $this->occurredAt = new \DateTimeImmutable();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    /** @return array<string, mixed> */
    public function getPayload(): array
    {
        return $this->payload;
    }

    public function getOccurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
