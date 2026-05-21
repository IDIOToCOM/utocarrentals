<?php

namespace App\Entity;

use App\Repository\CarFavoriteRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CarFavoriteRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_FAVORITE_USER_CAR', columns: ['login_id', 'car_id'])]
#[ORM\Table(name: 'car_favorite')]
class CarFavorite
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'login_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Login $user = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?CarInventory $car = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?Login
    {
        return $this->user;
    }

    public function setUser(Login $user): static
    {
        $this->user = $user;

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

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
