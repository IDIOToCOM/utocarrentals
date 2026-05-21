<?php

namespace App\Service;

use App\Booking\BookingStatus;
use App\Entity\Booking;
use App\Payment\PaymentStatus;

final class BookingCustomerRules
{
    public const HOURS_BEFORE_PICKUP_TO_MODIFY = 24;

    private readonly \DateTimeZone $timezone;

    public function __construct(string $appTimezone = 'Asia/Manila')
    {
        $this->timezone = new \DateTimeZone($appTimezone);
    }

    public function canCustomerEdit(Booking $booking): bool
    {
        return $this->getEditBlockReason($booking) === null;
    }

    public function canCustomerCancel(Booking $booking): bool
    {
        return $this->getCancelBlockReason($booking) === null;
    }

    /** @deprecated use canCustomerCancel() */
    public function canCustomerDelete(Booking $booking): bool
    {
        return $this->canCustomerCancel($booking);
    }

    public function canCustomerPay(Booking $booking, ?string $paymentStatus): bool
    {
        if (!BookingStatus::allowsCustomerEdit($booking->getStatus())) {
            return false;
        }

        return $paymentStatus === PaymentStatus::PENDING;
    }

    public function getEditBlockReason(Booking $booking): ?string
    {
        if (!BookingStatus::allowsCustomerEdit($booking->getStatus())) {
            return 'This booking can no longer be edited.';
        }

        return $this->pickupTooSoonReason($booking, 'edited');
    }

    public function getCancelBlockReason(Booking $booking): ?string
    {
        $status = $booking->getStatus();
        if ($status === BookingStatus::CANCELLED) {
            return 'This booking is already cancelled.';
        }
        if ($status === BookingStatus::REFUNDED) {
            return 'This booking was refunded and cannot be cancelled online.';
        }
        if (!BookingStatus::allowsCustomerEdit($status)) {
            return 'This booking can no longer be cancelled online.';
        }

        return $this->pickupTooSoonReason($booking, 'cancelled');
    }

    public function getHoursBeforePickupToModify(): int
    {
        return self::HOURS_BEFORE_PICKUP_TO_MODIFY;
    }

    public function isCancellableStatus(?string $status): bool
    {
        return $status === BookingStatus::PENDING || $status === BookingStatus::CONFIRMED;
    }

    /** @deprecated use getCancelBlockReason() */
    public function getDeleteBlockReason(Booking $booking): ?string
    {
        return $this->getCancelBlockReason($booking);
    }

    private function pickupTooSoonReason(Booking $booking, string $action): ?string
    {
        $pickup = $this->getPickupDateTime($booking);
        if (!$pickup instanceof \DateTimeImmutable) {
            return null;
        }

        $cutoff = (new \DateTimeImmutable('now', $this->timezone))
            ->modify('+'.self::HOURS_BEFORE_PICKUP_TO_MODIFY.' hours');

        if ($pickup < $cutoff) {
            return sprintf(
                'Bookings cannot be %s within %d hours of pickup. Contact us if you need help.',
                $action,
                self::HOURS_BEFORE_PICKUP_TO_MODIFY,
            );
        }

        return null;
    }

    public function getPickupDateTime(Booking $booking): ?\DateTimeImmutable
    {
        $pickupDate = $booking->getPickupDate();
        if (!$pickupDate instanceof \DateTimeInterface) {
            return null;
        }

        $pickup = \DateTimeImmutable::createFromInterface($pickupDate)->setTimezone($this->timezone);
        $pickupTime = $booking->getPickupTime();
        if ($pickupTime instanceof \DateTimeInterface) {
            $pickup = $pickup->setTime(
                (int) $pickupTime->format('H'),
                (int) $pickupTime->format('i'),
                0,
            );
        }

        return $pickup;
    }
}
