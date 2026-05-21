<?php

namespace App\Twig;

use App\Service\RentalPriceCalculator;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class RentalPriceExtension extends AbstractExtension
{
    public function __construct(
        private readonly RentalPriceCalculator $calculator,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('rental_days', $this->countRentalDays(...)),
            new TwigFunction('rental_estimate_total', $this->estimateTotal(...)),
            new TwigFunction('rental_min_total', $this->minimumTotal(...)),
        ];
    }

    public function countRentalDays(\DateTimeInterface $pickupDate, \DateTimeInterface $returnDate): int
    {
        return $this->calculator->countRentalDays($pickupDate, $returnDate);
    }

    public function estimateTotal(\DateTimeInterface $pickupDate, \DateTimeInterface $returnDate, ?int $pricePerDay): int
    {
        return $this->calculator->estimateTotal($pickupDate, $returnDate, $pricePerDay);
    }

    public function minimumTotal(?int $pricePerDay): int
    {
        return $this->calculator->minimumTotal($pricePerDay);
    }
}
