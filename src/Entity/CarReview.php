<?php

namespace App\Entity;

use App\Repository\CarReviewRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CarReviewRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_REVIEW_USER_CAR', columns: ['login_id', 'car_id'])]
#[ORM\Table(name: 'car_review')]
class CarReview
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'login_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Login $author = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?CarInventory $car = null;

    #[ORM\Column(type: 'smallint')]
    private int $rating = 5;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $comment = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAuthor(): ?Login
    {
        return $this->author;
    }

    public function setAuthor(Login $author): static
    {
        $this->author = $author;

        return $this;
    }

    public function getCar(): ?CarInventory
    {
        return $this->car;
    }

    public function setCar(CarInventory $car): static
    {
        $this->car = $car;

        return $this;
    }

    public function getRating(): int
    {
        return $this->rating;
    }

    public function setRating(int $rating): static
    {
        $this->rating = max(1, min(5, $rating));

        return $this;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): static
    {
        $this->comment = $comment !== null && trim($comment) !== '' ? trim($comment) : null;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function touchUpdatedAt(): static
    {
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function getAuthorDisplayName(): string
    {
        $author = $this->author;
        if ($author === null) {
            return 'Renter';
        }

        $name = trim((string) ($author->getDisplayName() ?? ''));
        if ($name !== '') {
            return $name;
        }

        return $author->getUsername() ?? 'Renter';
    }
}
