<?php

namespace App\Service;

/**
 * Rental billing: calendar days inclusive (pickup through return), minimum 1 day.
 * Matches admin payment creation in BookingController.
 */
final class RentalPriceCalculator
{
    public function countRentalDays(\DateTimeInterface $pickupDate, \DateTimeInterface $returnDate): int
    {
        $pickup = \DateTimeImmutable::createFromInterface($pickupDate)->setTime(0, 0, 0);
        $return = \DateTimeImmutable::createFromInterface($returnDate)->setTime(0, 0, 0);
        $diff = $pickup->diff($return);

        return max(1, $diff->days + 1);
    }

    public function estimateTotal(\DateTimeInterface $pickupDate, \DateTimeInterface $returnDate, ?int $pricePerDay): int
    {
        $rate = max(0, $pricePerDay ?? 0);

        return $this->countRentalDays($pickupDate, $returnDate) * $rate;
    }

    public function minimumTotal(?int $pricePerDay): int
    {
        return max(0, $pricePerDay ?? 0);
    }
}
