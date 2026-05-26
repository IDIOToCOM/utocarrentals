<?php

namespace App\Service;

use App\Booking\BookingStatus;
use App\Entity\AppNotification;
use App\Entity\Booking;
use App\Entity\Login;
use App\Notification\NotificationType;
use App\Payment\PaymentStatus;
use App\Repository\AppNotificationRepository;
use App\Repository\LoginRepository;
use Doctrine\ORM\EntityManagerInterface;

final class BookingNotificationService
{
    public function __construct(
        private readonly LoginRepository $loginRepository,
        private readonly AppNotificationRepository $notificationRepository,
        private readonly BookingCustomerRules $customerRules,
        private readonly string $appTimezone = 'Asia/Manila',
    ) {
    }

    public function notifyBookingSubmitted(Booking $booking, EntityManagerInterface $em): void
    {
        if ($booking->getId() === null) {
            return;
        }

        $summary = $this->bookingSummary($booking);

        $customer = $this->resolveCustomer($booking);
        if ($customer !== null) {
            $this->create(
                $customer,
                NotificationType::BOOKING_RECEIVED,
                'Booking received',
                sprintf(
                    'We received your rental request for %s. Status: Pending — we will review and confirm within 24 hours. %s',
                    $summary,
                    $this->scheduleLine($booking),
                ),
                'app_my_booking_show',
                ['id' => $booking->getId()],
                $booking,
                $em,
            );
        }

        foreach ($this->loginRepository->findStaffRecipients() as $staff) {
            $this->create(
                $staff,
                NotificationType::BOOKING_NEW_STAFF,
                'New booking submitted',
                sprintf(
                    'New rental request #%d — %s. %s',
                    $booking->getId(),
                    $summary,
                    $this->scheduleLine($booking),
                ),
                'app_booking_show',
                ['id' => $booking->getId()],
                $booking,
                $em,
            );
        }
    }

    public function notifyStatusChange(Booking $booking, string $previousStatus, EntityManagerInterface $em): void
    {
        if ($booking->getId() === null || $previousStatus === $booking->getStatus()) {
            return;
        }

        $summary = $this->bookingSummary($booking);
        $newStatus = $booking->getStatus();

        if ($newStatus === BookingStatus::CONFIRMED) {
            $this->notifyBookingConfirmed($booking, $em);
        } elseif ($newStatus === BookingStatus::CANCELLED) {
            $this->notifyBookingCancelled($booking, $em);
        } elseif ($newStatus === BookingStatus::REFUNDED) {
            $this->notifyBookingRefunded($booking, $em);
        } elseif ($newStatus === BookingStatus::COMPLETED) {
            $this->notifyBookingCompleted($booking, $em);
        }

        if ($newStatus === BookingStatus::CANCELLED && \in_array($previousStatus, [BookingStatus::PENDING, BookingStatus::CONFIRMED], true)) {
            foreach ($this->loginRepository->findStaffRecipients() as $staff) {
                if ($this->notificationRepository->existsForBookingRecipientAndType(
                    $booking->getId(),
                    (int) $staff->getId(),
                    NotificationType::BOOKING_CANCELLED_STAFF,
                )) {
                    continue;
                }
                $this->create(
                    $staff,
                    NotificationType::BOOKING_CANCELLED_STAFF,
                    'Booking cancelled',
                    sprintf('Booking #%d (%s) is now Cancelled.', $booking->getId(), $summary),
                    'app_booking_show',
                    ['id' => $booking->getId()],
                    $booking,
                    $em,
                );
            }
        }
    }

    public function notifyPaymentStatusChange(Booking $booking, ?string $previousPaymentStatus, ?string $newPaymentStatus, EntityManagerInterface $em): void
    {
        if ($previousPaymentStatus === $newPaymentStatus || $newPaymentStatus !== PaymentStatus::REFUNDED) {
            return;
        }

        $this->notifyBookingRefunded($booking, $em);
    }

    public function notifyBookingConfirmed(Booking $booking, EntityManagerInterface $em): void
    {
        $customer = $this->resolveCustomer($booking);
        if ($customer === null || $booking->getId() === null) {
            return;
        }

        $this->create(
            $customer,
            NotificationType::BOOKING_CONFIRMED,
            'Booking confirmed',
            sprintf('Your booking #%d has been confirmed.', $booking->getId()),
            'app_my_booking_show',
            ['id' => $booking->getId()],
            $booking,
            $em,
            false,
        );
    }

    public function notifyBookingCancelled(Booking $booking, EntityManagerInterface $em): void
    {
        $customer = $this->resolveCustomer($booking);
        if ($customer === null || $booking->getId() === null) {
            return;
        }

        $this->create(
            $customer,
            NotificationType::BOOKING_CANCELLED,
            'Booking cancelled',
            sprintf('Your booking #%d has been cancelled.', $booking->getId()),
            'app_my_booking_show',
            ['id' => $booking->getId()],
            $booking,
            $em,
            false,
        );
    }

    public function notifyBookingRefunded(Booking $booking, EntityManagerInterface $em): void
    {
        $customer = $this->resolveCustomer($booking);
        if ($customer === null || $booking->getId() === null) {
            return;
        }

        $this->create(
            $customer,
            NotificationType::BOOKING_REFUNDED,
            'Booking refunded',
            sprintf('Your booking #%d has been refunded.', $booking->getId()),
            'app_my_booking_show',
            ['id' => $booking->getId()],
            $booking,
            $em,
            false,
        );
    }

    private function notifyBookingCompleted(Booking $booking, EntityManagerInterface $em): void
    {
        $customer = $this->resolveCustomer($booking);
        if ($customer === null) {
            return;
        }

        $this->create(
            $customer,
            NotificationType::BOOKING_COMPLETED,
            'Rental completed',
            sprintf(
                'Your rental for %s is complete. Thank you for choosing UTO Car Rentals.',
                $this->bookingSummary($booking),
            ),
            'app_my_booking_show',
            ['id' => $booking->getId()],
            $booking,
            $em,
            false,
        );
    }

    public function notifyCustomerCancelled(Booking $booking, EntityManagerInterface $em): void
    {
        if ($booking->getId() === null) {
            return;
        }

        $summary = $this->bookingSummary($booking);

        $customer = $this->resolveCustomer($booking);
        if ($customer !== null) {
            $this->create(
                $customer,
                NotificationType::BOOKING_CANCELLED_CUSTOMER,
                'Booking cancelled',
                sprintf(
                    'You cancelled your booking for %s. The vehicle is no longer reserved for those dates.',
                    $summary,
                ),
                'app_my_booking_show',
                ['id' => $booking->getId()],
                $booking,
                $em,
            );
        }

        foreach ($this->loginRepository->findStaffRecipients() as $staff) {
            $this->create(
                $staff,
                NotificationType::BOOKING_CANCELLED_STAFF,
                'Customer cancelled booking',
                sprintf(
                    'Customer cancelled booking #%d — %s. %s',
                    $booking->getId(),
                    $summary,
                    $this->scheduleLine($booking),
                ),
                'app_booking_show',
                ['id' => $booking->getId()],
                $booking,
                $em,
            );
        }
    }

    /**
     * @return int Number of reminders created
     */
    public function sendPickupReminders(EntityManagerInterface $em, int $hoursAhead = 24): int
    {
        $tz = new \DateTimeZone($this->appTimezone);
        $now = new \DateTimeImmutable('now', $tz);
        $windowEnd = $now->modify(sprintf('+%d hours', $hoursAhead));

        $bookings = $em->createQueryBuilder()
            ->select('b')
            ->from(Booking::class, 'b')
            ->leftJoin('b.car', 'c')->addSelect('c')
            ->where('b.status = :confirmed')
            ->andWhere('b.pickupDate IS NOT NULL')
            ->setParameter('confirmed', BookingStatus::CONFIRMED)
            ->getQuery()
            ->getResult();

        $created = 0;
        foreach ($bookings as $booking) {
            if (!$booking instanceof Booking || $booking->getId() === null) {
                continue;
            }

            $pickup = $this->customerRules->getPickupDateTime($booking);
            if (!$pickup instanceof \DateTimeImmutable) {
                continue;
            }

            if ($pickup < $now || $pickup > $windowEnd) {
                continue;
            }

            $customer = $this->resolveCustomer($booking);
            if ($customer === null || $customer->getId() === null) {
                continue;
            }

            if ($this->notificationRepository->existsForBookingRecipientAndType(
                $booking->getId(),
                (int) $customer->getId(),
                NotificationType::PICKUP_REMINDER,
            )) {
                continue;
            }

            $this->create(
                $customer,
                NotificationType::PICKUP_REMINDER,
                'Pickup reminder',
                sprintf(
                    'Reminder: pickup for %s is %s. Location: %s.',
                    $this->bookingSummary($booking),
                    $pickup->format('l, M j \a\t g:i A'),
                    $booking->getPickupLocation() ?? 'see your booking',
                ),
                'app_my_booking_show',
                ['id' => $booking->getId()],
                $booking,
                $em,
            );
            ++$created;
        }

        return $created;
    }

    private function create(
        Login $recipient,
        string $type,
        string $title,
        string $body,
        ?string $linkRoute,
        ?array $linkParams,
        ?Booking $booking,
        EntityManagerInterface $em,
        bool $dedupe = true,
    ): void {
        if ($recipient->getId() === null) {
            return;
        }

        if ($dedupe
            && $booking !== null
            && $booking->getId() !== null
            && $this->notificationRepository->existsForBookingRecipientAndType(
                $booking->getId(),
                (int) $recipient->getId(),
                $type,
            )) {
            return;
        }

        $notification = new AppNotification();
        $notification->setRecipient($recipient);
        $notification->setType($type);
        $notification->setTitle($title);
        $notification->setBody($body);
        $notification->setLinkRoute($linkRoute);
        $notification->setLinkParams($linkParams);
        $notification->setBooking($booking);

        $em->persist($notification);
    }

    private function resolveCustomer(Booking $booking): ?Login
    {
        $createdBy = $booking->getCreatedBy();
        if ($createdBy instanceof Login && $this->isCustomerAccount($createdBy)) {
            return $createdBy;
        }

        return $this->loginRepository->findCustomerByPhone($booking->getPhone());
    }

    private function isCustomerAccount(Login $login): bool
    {
        $roles = $login->getRoles();

        return !\in_array('ROLE_ADMIN', $roles, true) && !\in_array('ROLE_STAFF', $roles, true);
    }

    private function bookingSummary(Booking $booking): string
    {
        $car = $booking->getCar();
        if ($car !== null) {
            return trim($car->getBrand().' '.$car->getModel());
        }

        return 'booking #'.($booking->getId() ?? '?');
    }

    private function scheduleLine(Booking $booking): string
    {
        $pickup = $booking->getPickupDate();
        $return = $booking->getReturnDate();
        if ($pickup === null || $return === null) {
            return '';
        }

        $line = sprintf(
            'Pickup %s',
            $pickup->format('M j, Y'),
        );
        if ($booking->getPickupTime() !== null) {
            $line .= ' '.$booking->getPickupTime()->format('g:i A');
        }
        $line .= sprintf(' → return %s', $return->format('M j, Y'));
        if ($booking->getReturnTime() !== null) {
            $line .= ' '.$booking->getReturnTime()->format('g:i A');
        }

        return $line.'.';
    }
}
