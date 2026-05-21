<?php

namespace App\Car;

/**
 * Estimated rental total for a catalog date window (calendar days × daily rate).
 */
final class CatalogRentalEstimate
{
    public function __construct(
        public readonly int $days,
        public readonly int $total,
        public readonly int $pricePerDay,
    ) {
    }
}
