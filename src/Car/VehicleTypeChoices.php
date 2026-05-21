<?php

namespace App\Car;

/**
 * Canonical vehicle types — shared by admin car form and customer catalog filters.
 */
final class VehicleTypeChoices
{
    /** @var list<string> */
    public const ALL = [
        'SUV',
        'Sports Car',
        'Sedan',
        'Hatchback',
        'Truck',
    ];

    /**
     * @return array<string, string> label => value for Symfony ChoiceType
     */
    public static function forForm(): array
    {
        $choices = [];
        foreach (self::ALL as $type) {
            $choices[$type] = $type;
        }

        return $choices;
    }
}
