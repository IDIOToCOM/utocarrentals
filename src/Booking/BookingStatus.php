<?php

namespace App\Booking;

/**
 * Rental booking lifecycle — stored as plain strings on {@see \App\Entity\Booking}.
 */
final class BookingStatus
{
    public const PENDING = 'Pending';

    public const CONFIRMED = 'Confirmed';

    public const CANCELLED = 'Cancelled';

    public const REFUNDED = 'Refunded';

    public const COMPLETED = 'Completed';

    /** @var list<string> */
    private const INACTIVE = [
        self::CANCELLED,
        self::REFUNDED,
        self::COMPLETED,
    ];

    /**
     * @return array<string, string> label => stored value
     */
    public static function forForm(): array
    {
        return [
            'Pending' => self::PENDING,
            'Confirmed' => self::CONFIRMED,
            'Cancelled' => self::CANCELLED,
            'Refunded' => self::REFUNDED,
            'Completed' => self::COMPLETED,
        ];
    }

    public static function blocksVehicleAvailability(?string $status): bool
    {
        return !\in_array($status, self::INACTIVE, true);
    }

    public static function allowsCustomerEdit(?string $status): bool
    {
        return !\in_array($status, self::INACTIVE, true);
    }
}
