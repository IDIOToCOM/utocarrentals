<?php

namespace App\Notification;

final class NotificationType
{
    public const BOOKING_RECEIVED = 'booking_received';

    public const BOOKING_CONFIRMED = 'booking_confirmed';

    public const BOOKING_DECLINED = 'booking_declined';

    public const BOOKING_CANCELLED_CUSTOMER = 'booking_cancelled_customer';

    public const BOOKING_CANCELLED_STAFF = 'booking_cancelled_staff';

    public const PICKUP_REMINDER = 'pickup_reminder';

    public const BOOKING_NEW_STAFF = 'booking_new_staff';
}
