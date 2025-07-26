<?php

namespace App\Entity;

use App\Repository\EventLogRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EventLogRepository::class)]
#[ORM\Index(name: 'idx_event_type', columns: ['event_type'])]
#[ORM\Index(name: 'idx_created_at', columns: ['created_at'])]
class EventLog
{
    // Event types
    public const TYPE_USER_REGISTERED = 'user.registered';
    public const TYPE_USER_LOGIN = 'user.login';
    public const TYPE_USER_LOGOUT = 'user.logout';
    
    public const TYPE_EVENT_CREATED = 'event.created';
    public const TYPE_EVENT_UPDATED = 'event.updated';
    public const TYPE_EVENT_DELETED = 'event.deleted';
    public const TYPE_EVENT_PARTICIPANT_ADDED = 'event.participant.added';
    public const TYPE_EVENT_PARTICIPANT_REMOVED = 'event.participant.removed';
    
    public const TYPE_POST_CREATED = 'post.created';
    public const TYPE_POST_UPDATED = 'post.updated';
    public const TYPE_POST_PUBLISHED = 'post.published';
    public const TYPE_POST_DELETED = 'post.deleted';
    
    public const TYPE_COMMENT_CREATED = 'comment.created';
    public const TYPE_COMMENT_UPDATED = 'comment.updated';
    public const TYPE_COMMENT_HIDDEN = 'comment.hidden';
    public const TYPE_COMMENT_DELETED = 'comment.deleted';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private ?string $eventType = null;

    #[ORM\Column(type: Types::JSON)]
    private array $payload = [];

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $user = null;

    #[ORM\Column(length: 45, nullable: true)]
    private ?string $ipAddress = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $userAgent = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEventType(): ?string
    {
        return $this->eventType;
    }

    public function setEventType(string $eventType): static
    {
        $this->eventType = $eventType;

        return $this;
    }

    public function getPayload(): array
    {
        return $this->payload;
    }

    public function setPayload(array $payload): static
    {
        $this->payload = $payload;

        return $this;
    }

    public function addPayloadData(string $key, mixed $value): static
    {
        $this->payload[$key] = $value;

        return $this;
    }

    public function getPayloadData(string $key): mixed
    {
        return $this->payload[$key] ?? null;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function setIpAddress(?string $ipAddress): static
    {
        $this->ipAddress = $ipAddress;

        return $this;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function setUserAgent(?string $userAgent): static
    {
        $this->userAgent = $userAgent;

        return $this;
    }

    public static function getAvailableEventTypes(): array
    {
        return [
            self::TYPE_USER_REGISTERED,
            self::TYPE_USER_LOGIN,
            self::TYPE_USER_LOGOUT,
            self::TYPE_EVENT_CREATED,
            self::TYPE_EVENT_UPDATED,
            self::TYPE_EVENT_DELETED,
            self::TYPE_EVENT_PARTICIPANT_ADDED,
            self::TYPE_EVENT_PARTICIPANT_REMOVED,
            self::TYPE_POST_CREATED,
            self::TYPE_POST_UPDATED,
            self::TYPE_POST_PUBLISHED,
            self::TYPE_POST_DELETED,
            self::TYPE_COMMENT_CREATED,
            self::TYPE_COMMENT_UPDATED,
            self::TYPE_COMMENT_HIDDEN,
            self::TYPE_COMMENT_DELETED,
        ];
    }
}
