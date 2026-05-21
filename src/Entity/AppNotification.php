<?php

namespace App\Entity;

use App\Repository\AppNotificationRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AppNotificationRepository::class)]
#[ORM\Table(name: 'app_notification')]
#[ORM\Index(name: 'IDX_NOTIF_RECIPIENT_READ', columns: ['recipient_id', 'read_at'])]
class AppNotification
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Login $recipient = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Booking $booking = null;

    #[ORM\Column(length: 64)]
    private string $type = '';

    #[ORM\Column(length: 255)]
    private string $title = '';

    #[ORM\Column(type: 'text')]
    private string $body = '';

    #[ORM\Column(length: 128, nullable: true)]
    private ?string $linkRoute = null;

    /** @var array<string, int|string>|null */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $linkParams = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $readAt = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRecipient(): ?Login
    {
        return $this->recipient;
    }

    public function setRecipient(Login $recipient): static
    {
        $this->recipient = $recipient;

        return $this;
    }

    public function getBooking(): ?Booking
    {
        return $this->booking;
    }

    public function setBooking(?Booking $booking): static
    {
        $this->booking = $booking;

        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function setBody(string $body): static
    {
        $this->body = $body;

        return $this;
    }

    public function getLinkRoute(): ?string
    {
        return $this->linkRoute;
    }

    public function setLinkRoute(?string $linkRoute): static
    {
        $this->linkRoute = $linkRoute;

        return $this;
    }

    /**
     * @return array<string, int|string>|null
     */
    public function getLinkParams(): ?array
    {
        return $this->linkParams;
    }

    /**
     * @param array<string, int|string>|null $linkParams
     */
    public function setLinkParams(?array $linkParams): static
    {
        $this->linkParams = $linkParams;

        return $this;
    }

    public function getReadAt(): ?\DateTimeImmutable
    {
        return $this->readAt;
    }

    public function setReadAt(?\DateTimeImmutable $readAt): static
    {
        $this->readAt = $readAt;

        return $this;
    }

    public function isRead(): bool
    {
        return $this->readAt !== null;
    }

    public function markRead(): static
    {
        $this->readAt = new \DateTimeImmutable();

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
