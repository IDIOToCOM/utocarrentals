<?php

namespace App\Service;

use App\Car\CatalogAvailabilityQuery;
use App\Car\CatalogRentalEstimate;

final class CatalogRentalEstimateBuilder
{
    public function __construct(
        private readonly RentalPriceCalculator $rentalPriceCalculator,
    ) {
    }

    public function fromQuery(CatalogAvailabilityQuery $query, ?int $pricePerDay): ?CatalogRentalEstimate
    {
        if (!$query->isFiltering() || $pricePerDay === null) {
            return null;
        }

        $pickup = \DateTimeImmutable::createFromFormat('!Y-m-d', $query->pickupDate ?? '');
        $return = \DateTimeImmutable::createFromFormat('!Y-m-d', $query->returnDate ?? '');
        if (!$pickup || !$return) {
            return null;
        }

        $rate = max(0, $pricePerDay);
        $days = $this->rentalPriceCalculator->countRentalDays($pickup, $return);
        $total = $this->rentalPriceCalculator->estimateTotal($pickup, $return, $rate);

        return new CatalogRentalEstimate($days, $total, $rate);
    }
}
