<?php

namespace App\Payment;

final class PaymentStatus
{
    public const PENDING = 'Pending';

    public const COMPLETED = 'Completed';

    public const FAILED = 'Failed';

    public const REFUNDED = 'Refunded';

    public static function isPaid(?string $status): bool
    {
        return $status === self::COMPLETED;
    }

    public static function customerLabel(?string $status): string
    {
        return match ($status) {
            self::COMPLETED => 'Paid',
            self::PENDING => 'Pending',
            self::REFUNDED => 'Refunded',
            self::FAILED => 'Failed',
            default => $status ?? 'Pending',
        };
    }
}
