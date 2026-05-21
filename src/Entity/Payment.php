<?php

namespace App\Entity;

use App\Payment\PaymentStatus;
use App\Repository\PaymentRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PaymentRepository::class)]
class Payment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\ManyToOne(inversedBy: 'payments')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?CarInventory $car = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Booking $booking = null;

    #[ORM\Column]
    private int $amountDue = 0;

    #[ORM\Column(nullable: true)]
    private ?int $amountPaid = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTime $paidAt = null;

    #[ORM\Column(length: 255)]
    private ?string $status = null;

    #[ORM\ManyToOne(targetEntity: Login::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Login $createdBy = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getCar(): ?CarInventory
    {
        return $this->car;
    }

    public function setCar(?CarInventory $car): static
    {
        $this->car = $car;

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

    public function getAmountDue(): int
    {
        return $this->amountDue;
    }

    public function setAmountDue(int $amountDue): static
    {
        $this->amountDue = max(0, $amountDue);

        return $this;
    }

    public function getAmountPaid(): ?int
    {
        return $this->amountPaid;
    }

    public function setAmountPaid(?int $amountPaid): static
    {
        $this->amountPaid = $amountPaid !== null ? max(0, $amountPaid) : null;

        return $this;
    }

    public function getPaidAt(): ?\DateTime
    {
        return $this->paidAt;
    }

    public function setPaidAt(?\DateTime $paidAt): static
    {
        $this->paidAt = $paidAt;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function isPaid(): bool
    {
        return PaymentStatus::isPaid($this->status);
    }

    public function getCreatedBy(): ?Login
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?Login $createdBy): static
    {
        $this->createdBy = $createdBy;

        return $this;
    }
}
