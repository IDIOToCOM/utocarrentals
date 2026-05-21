<?php

namespace App\Car;

/**
 * Fleet visibility for catalog — not the same as booking date availability.
 */
final class CarFleetStatus
{
    public const AVAILABLE = 'Available';

    public const OUT_OF_SERVICE = 'Out of service';

    /**
     * @return array<string, string> label => stored value
     */
    public static function forForm(): array
    {
        return [
            'Available — shown to customers' => self::AVAILABLE,
            'Out of service — hidden from customers' => self::OUT_OF_SERVICE,
        ];
    }

    public static function isVisibleToCustomers(?string $status): bool
    {
        return $status !== null
            && strcasecmp(trim($status), self::AVAILABLE) === 0;
    }
}
