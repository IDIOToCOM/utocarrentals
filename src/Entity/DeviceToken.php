<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\DeviceTokenRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DeviceTokenRepository::class)]
#[ORM\Table(name: 'device_token')]
#[ORM\UniqueConstraint(name: 'uniq_fcm_token', columns: ['fcm_token'])]
#[ORM\Index(name: 'idx_device_token_login', columns: ['login_id'])]
class DeviceToken
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Login $login;

    #[ORM\Column(length: 512)]
    private string $fcmToken = '';

    #[ORM\Column(length: 16)]
    private string $platform = 'android';

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLogin(): Login
    {
        return $this->login;
    }

    public function setLogin(Login $login): static
    {
        $this->login = $login;

        return $this;
    }

    public function getFcmToken(): string
    {
        return $this->fcmToken;
    }

    public function setFcmToken(string $fcmToken): static
    {
        $this->fcmToken = $fcmToken;

        return $this;
    }

    public function getPlatform(): string
    {
        return $this->platform;
    }

    public function setPlatform(string $platform): static
    {
        $this->platform = $platform;

        return $this;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function touch(): static
    {
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }
}
