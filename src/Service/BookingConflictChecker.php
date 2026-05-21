<?php

namespace App\Service;

use App\Entity\Booking;
use App\Repository\BookingRepository;

/**
 * Detects when a new rental overlaps an existing one for the same car.
 * Return day is available for the next pickup (half-open date range [pickup, return)).
 */
final class BookingConflictChecker
{
    public const MESSAGE = 'This vehicle is already booked for part of your selected dates. Please choose different dates or pick another vehicle from our catalog.';

    public function __construct(
        private readonly BookingRepository $bookingRepository,
    ) {
    }

    public function hasConflict(
        int $carId,
        \DateTimeInterface $pickupDate,
        \DateTimeInterface $returnDate,
        ?\DateTimeInterface $pickupTime = null,
        ?\DateTimeInterface $returnTime = null,
        ?int $excludeBookingId = null,
    ): bool {
        $pickup = $this->normalizeDate($pickupDate);
        $return = $this->normalizeDate($returnDate);

        foreach ($this->bookingRepository->findByCarId($carId) as $existing) {
            if ($excludeBookingId !== null && $existing->getId() === $excludeBookingId) {
                continue;
            }

            if (!$existing->blocksVehicleAvailability()) {
                continue;
            }

            if ($this->bookingOverlaps($pickup, $return, $pickupTime, $returnTime, $existing)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{blockedDates: list<string>, bookedRanges: list<array{pickup: string, return: string}>}
     */
    public function getCalendarData(int $carId): array
    {
        return [
            'blockedDates' => $this->getBlockedPickupDates($carId),
            'bookedRanges' => $this->getBookedRanges($carId),
        ];
    }

    /**
     * Dates the car is out (pickup through day before return). Return day stays available.
     *
     * @return list<string> Y-m-d
     */
    public function getBlockedPickupDates(int $carId): array
    {
        $blocked = [];

        foreach ($this->bookingRepository->findByCarId($carId) as $booking) {
            if (!$booking->blocksVehicleAvailability()) {
                continue;
            }

            foreach ($this->expandOccupiedDays($booking) as $day) {
                $blocked[$day] = true;
            }
        }

        $dates = array_keys($blocked);
        sort($dates);

        return $dates;
    }

    /**
     * @return list<array{pickup: string, return: string}>
     */
    public function getBookedRanges(int $carId): array
    {
        $ranges = [];

        foreach ($this->bookingRepository->findByCarId($carId) as $booking) {
            if (!$booking->blocksVehicleAvailability()) {
                continue;
            }

            $pickup = $booking->getPickupDate();
            $return = $booking->getReturnDate();
            if ($pickup === null || $return === null) {
                continue;
            }

            $ranges[] = [
                'pickup' => $pickup->format('Y-m-d'),
                'return' => $return->format('Y-m-d'),
            ];
        }

        return $ranges;
    }

    /**
     * @return list<string> Y-m-d
     */
    private function expandOccupiedDays(Booking $booking): array
    {
        $pickup = $booking->getPickupDate();
        $return = $booking->getReturnDate();
        if ($pickup === null || $return === null) {
            return [];
        }

        $days = [];
        $current = $this->normalizeDate($pickup);
        $end = $this->normalizeDate($return);

        while ($current < $end) {
            $days[] = $current->format('Y-m-d');
            $current = $current->modify('+1 day');
        }

        return $days;
    }

    private function bookingOverlaps(
        \DateTimeImmutable $pickup,
        \DateTimeImmutable $return,
        ?\DateTimeInterface $pickupTime,
        ?\DateTimeInterface $returnTime,
        Booking $existing,
    ): bool {
        $existingPickup = $existing->getPickupDate();
        $existingReturn = $existing->getReturnDate();
        if ($existingPickup === null || $existingReturn === null) {
            return false;
        }

        $ep = $this->normalizeDate($existingPickup);
        $er = $this->normalizeDate($existingReturn);

        // Half-open date ranges [pickup, return)
        if ($pickup < $er && $return > $ep) {
            if ($pickup == $er) {
                return !$this->newPickupAllowedOnReturnDay($pickupTime, $existing->getReturnTime());
            }

            if ($return == $ep) {
                return !$this->newReturnAllowedOnPickupDay($returnTime, $existing->getPickupTime());
            }

            return true;
        }

        return $this->sameCalendarDayTimeOverlap(
            $pickup,
            $return,
            $pickupTime,
            $returnTime,
            $ep,
            $er,
            $existing->getPickupTime(),
            $existing->getReturnTime(),
        );
    }

    private function newPickupAllowedOnReturnDay(?\DateTimeInterface $newPickupTime, ?\DateTimeInterface $existingReturnTime): bool
    {
        if ($newPickupTime === null || $existingReturnTime === null) {
            return false;
        }

        return $this->timeToMinutes($newPickupTime) >= $this->timeToMinutes($existingReturnTime);
    }

    private function newReturnAllowedOnPickupDay(?\DateTimeInterface $newReturnTime, ?\DateTimeInterface $existingPickupTime): bool
    {
        if ($newReturnTime === null || $existingPickupTime === null) {
            return false;
        }

        return $this->timeToMinutes($newReturnTime) <= $this->timeToMinutes($existingPickupTime);
    }

    private function sameCalendarDayTimeOverlap(
        \DateTimeImmutable $pickup,
        \DateTimeImmutable $return,
        ?\DateTimeInterface $pickupTime,
        ?\DateTimeInterface $returnTime,
        \DateTimeImmutable $ep,
        \DateTimeImmutable $er,
        ?\DateTimeInterface $existingPickupTime,
        ?\DateTimeInterface $existingReturnTime,
    ): bool {
        if ($pickupTime === null || $returnTime === null || $existingPickupTime === null || $existingReturnTime === null) {
            return false;
        }

        $newSameDay = $pickup == $return;
        $existingSameDay = $ep == $er;

        if (!$newSameDay && !$existingSameDay) {
            return false;
        }

        if ($newSameDay && $existingSameDay && $pickup == $ep) {
            return $this->timeRangesOverlap(
                $this->timeToMinutes($pickupTime),
                $this->timeToMinutes($returnTime),
                $this->timeToMinutes($existingPickupTime),
                $this->timeToMinutes($existingReturnTime),
            );
        }

        return false;
    }

    private function timeRangesOverlap(int $startA, int $endA, int $startB, int $endB): bool
    {
        return $startA < $endB && $endA > $startB;
    }

    private function timeToMinutes(\DateTimeInterface $time): int
    {
        return (int) $time->format('H') * 60 + (int) $time->format('i');
    }

    private function normalizeDate(\DateTimeInterface $date): \DateTimeImmutable
    {
        return \DateTimeImmutable::createFromInterface($date)->setTime(0, 0, 0);
    }
}
