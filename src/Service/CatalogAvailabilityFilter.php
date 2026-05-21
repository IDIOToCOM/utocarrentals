<?php

namespace App\Service;

use App\Car\CatalogAvailabilityQuery;
use App\Entity\CarInventory;

/**
 * Filters catalog vehicles that are free for a requested rental window.
 */
final class CatalogAvailabilityFilter
{
    public function __construct(
        private readonly BookingScheduleValidator $scheduleValidator,
        private readonly BookingConflictChecker $conflictChecker,
    ) {
    }

    /**
     * @return string|null Error message when query is incomplete or invalid
     */
    public function resolveCatalogError(CatalogAvailabilityQuery $query): ?string
    {
        if ($query->hasPartialInput()) {
            return 'Enter pickup and return dates and times to filter by availability.';
        }

        if (!$query->isFiltering()) {
            return null;
        }

        return $this->scheduleValidator->validateRentalSchedule(
            $query->pickupDate,
            $query->returnDate,
            $query->pickupTime,
            $query->returnTime,
        );
    }

    /**
     * @param list<CarInventory> $cars
     *
     * @return list<CarInventory>
     */
    public function filterAvailable(array $cars, CatalogAvailabilityQuery $query): array
    {
        if (!$query->isFiltering()) {
            return $cars;
        }

        $pickupDate = \DateTimeImmutable::createFromFormat('!Y-m-d', $query->pickupDate);
        $returnDate = \DateTimeImmutable::createFromFormat('!Y-m-d', $query->returnDate);
        $pickupTime = $this->scheduleValidator->parseTimeOnly($query->pickupTime);
        $returnTime = $this->scheduleValidator->parseTimeOnly($query->returnTime);

        if (!$pickupDate || !$returnDate || !$pickupTime || !$returnTime) {
            return $cars;
        }

        $available = [];
        foreach ($cars as $car) {
            $carId = $car->getId();
            if ($carId === null) {
                continue;
            }
            if (!$this->conflictChecker->hasConflict($carId, $pickupDate, $returnDate, $pickupTime, $returnTime)) {
                $available[] = $car;
            }
        }

        return $available;
    }
}
