<?php

namespace App\Entity;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use App\Booking\BookingStatus;
use App\Repository\BookingRepository;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\Login;

#[ORM\Entity(repositoryClass: BookingRepository::class)]
class Booking
{
    public const STATUS_PENDING = BookingStatus::PENDING;
    public const STATUS_CONFIRMED = BookingStatus::CONFIRMED;
    public const STATUS_CANCELLED = BookingStatus::CANCELLED;
    public const STATUS_REFUNDED = BookingStatus::REFUNDED;
    public const STATUS_COMPLETED = BookingStatus::COMPLETED;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $name = null;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(length: 255)]
    private ?string $pickupLocation = null;

    #[ORM\Column(length: 255)]
    private ?string $dropoffLocation = null;

    #[ORM\Column]
    private ?\DateTime $pickupDate = null;

    #[ORM\Column]
    private ?\DateTime $returnDate = null;

    #[ORM\ManyToOne(inversedBy: 'bookings')]
    #[ORM\JoinColumn(nullable: true)]
    private ?CarInventory $car = null;

    #[ORM\ManyToOne(inversedBy: 'Bookings')]
    private ?User $user = null;

   #[ORM\Column(type: 'time')]
    private ?\DateTime $pickupTime = null;

   #[ORM\Column(type: 'time')]
    private ?\DateTime $returnTime = null;

    #[ORM\ManyToOne(targetEntity: Login::class)]
    #[ORM\JoinColumn(name: 'created_by_id', nullable: true)]
    private ?Login $createdBy = null;

    #[ORM\Column(length: 20)]
    private string $status = self::STATUS_PENDING;

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

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(string $phone): static
    {
        $this->phone = $phone;

        return $this;
    }

    public function getPickupLocation(): ?string
    {
        return $this->pickupLocation;
    }

    public function setPickupLocation(string $pickupLocation): static
    {
        $this->pickupLocation = $pickupLocation;

        return $this;
    }

    public function getDropoffLocation(): ?string
    {
        return $this->dropoffLocation;
    }

    public function setDropoffLocation(string $dropoffLocation): static
    {
        $this->dropoffLocation = $dropoffLocation;

        return $this;
    }

    public function getPickupDate(): ?\DateTime
    {
        return $this->pickupDate;
    }

    public function setPickupDate(\DateTimeInterface $pickupDate): static
    {
        $this->pickupDate = $pickupDate instanceof \DateTime ? $pickupDate : \DateTime::createFromInterface($pickupDate);

        return $this;
    }

    public function getReturnDate(): ?\DateTime
    {
        return $this->returnDate;
    }

    public function setReturnDate(\DateTimeInterface $returnDate): static
    {
        $this->returnDate = $returnDate instanceof \DateTime ? $returnDate : \DateTime::createFromInterface($returnDate);

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

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getPickupTime(): ?\DateTime
    {
        return $this->pickupTime;
    }

    public function setPickupTime(\DateTimeInterface $pickupTime): static
    {
        $this->pickupTime = $pickupTime instanceof \DateTime ? $pickupTime : \DateTime::createFromInterface($pickupTime);

        return $this;
    }

    public function getReturnTime(): ?\DateTime
    {
        return $this->returnTime;
    }

    public function setReturnTime(\DateTimeInterface $returnTime): static
    {
        $this->returnTime = $returnTime instanceof \DateTime ? $returnTime : \DateTime::createFromInterface($returnTime);

        return $this;
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

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function isEditableByCustomer(): bool
    {
        return BookingStatus::allowsCustomerEdit($this->status);
    }

    public function blocksVehicleAvailability(): bool
    {
        return BookingStatus::blocksVehicleAvailability($this->status);
    }

        /**
     * @Assert\Callback
     */
    public function validateBooking(ExecutionContextInterface $context, $payload): void
    {
        if (!$this->pickupDate || !$this->returnDate || !$this->pickupTime || !$this->returnTime) {
            return;
        }

        $pickupDateTime = clone $this->pickupDate;
        $pickupDateTime->setTime(
            (int) $this->pickupTime->format('H'),
            (int) $this->pickupTime->format('i'),
            0
        );

        $returnDateTime = clone $this->returnDate;
        $returnDateTime->setTime(
            (int) $this->returnTime->format('H'),
            (int) $this->returnTime->format('i'),
            0
        );

        $now = new \DateTime();

        if ($pickupDateTime < $now) {
            $context->buildViolation('Pickup date/time cannot be in the past.')
                ->atPath('pickupDate')
                ->addViolation();
        }

        if ($returnDateTime < $now) {
            $context->buildViolation('Return date/time cannot be in the past.')
                ->atPath('returnDate')
                ->addViolation();
        }

        if ($returnDateTime < $pickupDateTime) {
            $context->buildViolation('Return date/time cannot be before pickup date/time.')
                ->atPath('returnDate')
                ->addViolation();
        }

        $pickupMinutes = (int) $this->pickupTime->format('i');
        $returnMinutes = (int) $this->returnTime->format('i');
        if ($pickupMinutes % 30 !== 0 || (int) $this->pickupTime->format('s') !== 0) {
            $context->buildViolation('Pickup time must use 30-minute intervals (e.g. 09:00 or 09:30).')
                ->atPath('pickupTime')
                ->addViolation();
        }
        if ($returnMinutes % 30 !== 0 || (int) $this->returnTime->format('s') !== 0) {
            $context->buildViolation('Return time must use 30-minute intervals (e.g. 09:00 or 09:30).')
                ->atPath('returnTime')
                ->addViolation();
        }

        $rentalSeconds = $returnDateTime->getTimestamp() - $pickupDateTime->getTimestamp();
        if ($rentalSeconds < 30 * 60) {
            $context->buildViolation('Rental must be at least 30 minutes.')
                ->atPath('returnDate')
                ->addViolation();
        }
    }


}
