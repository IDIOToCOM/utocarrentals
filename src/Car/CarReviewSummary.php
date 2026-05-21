<?php

namespace App\Car;

/** Aggregated customer review stats for catalog display. */
final class CarReviewSummary
{
    public function __construct(
        public readonly int $reviewCount = 0,
        public readonly ?float $averageRating = null,
    ) {
    }

    public function hasReviews(): bool
    {
        return $this->reviewCount > 0;
    }

    public function getStarRatingDisplay(): ?float
    {
        if ($this->averageRating === null) {
            return null;
        }

        return round($this->averageRating * 2) / 2;
    }
}
