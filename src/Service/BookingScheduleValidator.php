<?php

namespace App\Service;

use App\Entity\Booking;

/**
 * Validates rental pickup/return using local app timezone (date + time together).
 */
final class BookingScheduleValidator
{
    private readonly \DateTimeZone $timezone;

    public function __construct(string $appTimezone = 'Asia/Manila')
    {
        $this->timezone = new \DateTimeZone($appTimezone);
    }

    /**
     * @return array<string, string> Field key => message
     */
    public function validateSubmission(
        string $customerName,
        string $phone,
        string $pickupLocation,
        string $dropoffLocation,
        string $pickupDate,
        string $returnDate,
        string $pickupTime,
        string $returnTime,
    ): array {
        $errors = [];

        if (trim($customerName) === '') {
            $errors['name'] = 'Your name is required.';
        }

        $phoneDigits = preg_replace('/\D+/', '', $phone) ?? '';
        if (trim($phone) === '') {
            $errors['phone'] = 'Phone number is required.';
        } elseif (strlen($phoneDigits) < 10) {
            $errors['phone'] = 'Enter a valid phone number (at least 10 digits).';
        }

        if (trim($pickupLocation) === '') {
            $errors['pickupLocation'] = 'Pickup location is required.';
        }

        if (trim($dropoffLocation) === '') {
            $errors['dropoffLocation'] = 'Drop-off location is required.';
        }

        if (trim($pickupDate) === '' || trim($returnDate) === '') {
            $errors['rentalDates'] = 'Please select a rental period (pickup and return dates).';
        }

        if (trim($pickupTime) === '') {
            $errors['pickupTime'] = 'Pickup time is required.';
        }

        if (trim($returnTime) === '') {
            $errors['returnTime'] = 'Return time is required.';
        }

        if (!empty($errors)) {
            return $errors;
        }

        $scheduleError = $this->validateRentalSchedule($pickupDate, $returnDate, $pickupTime, $returnTime);
        if ($scheduleError !== null) {
            $errors['schedule'] = $scheduleError;
        }

        return $errors;
    }

    /**
     * Validates pickup/return dates and times only (catalog filter, conflict checks).
     *
     * @return string|null First error message
     */
    public function validateRentalSchedule(
        string $pickupDate,
        string $returnDate,
        string $pickupTime,
        string $returnTime,
    ): ?string {
        $pickupDateTime = $this->combineDateAndTime($pickupDate, $pickupTime);
        if (!$pickupDateTime instanceof \DateTime) {
            return 'Enter a valid pickup date and time.';
        }

        $returnDateTime = $this->combineDateAndTime($returnDate, $returnTime);
        if (!$returnDateTime instanceof \DateTime) {
            return 'Enter a valid return date and time.';
        }

        $now = $this->now();

        if ($pickupDateTime < $now) {
            return 'Pickup cannot be in the past. Choose a later date or time.';
        }

        if ($returnDateTime < $now) {
            return 'Return cannot be in the past. Choose a later date or time.';
        }

        if ($returnDateTime < $pickupDateTime) {
            return 'Return date/time cannot be before pickup date/time.';
        }

        if (!$this->isThirtyMinuteSlot($pickupDateTime)) {
            return 'Pickup time must use 30-minute intervals (e.g. 09:00 or 09:30).';
        }

        if (!$this->isThirtyMinuteSlot($returnDateTime)) {
            return 'Return time must use 30-minute intervals (e.g. 09:00 or 09:30).';
        }

        if ($returnDateTime->getTimestamp() - $pickupDateTime->getTimestamp() < 30 * 60) {
            return 'Rental must be at least 30 minutes.';
        }

        return null;
    }

    private function isThirtyMinuteSlot(\DateTime $dateTime): bool
    {
        $minutes = (int) $dateTime->format('i');
        $seconds = (int) $dateTime->format('s');

        return $minutes % 30 === 0 && $seconds === 0;
    }

    public function combineDateAndTime(string $dateYmd, string $timeInput): ?\DateTime
    {
        $dateYmd = trim($dateYmd);
        $timeInput = trim(preg_replace('/\s+/u', ' ', str_replace("\xc2\xa0", ' ', $timeInput)));

        if ($dateYmd === '' || $timeInput === '') {
            return null;
        }

        // 12-hour (AM/PM) first — avoids treating "12:00 AM" as noon via H:i.
        $formats = [
            'h:i A', 'g:i A', 'h:i a', 'g:i a', 'h:iA', 'g:iA', 'h:ia', 'g:ia',
            'H:i', 'H:i:s',
        ];

        foreach ($formats as $tf) {
            $dt = \DateTime::createFromFormat('!Y-m-d '.$tf, $dateYmd.' '.$timeInput, $this->timezone);
            if ($dt instanceof \DateTime) {
                return $dt;
            }
        }

        return null;
    }

    public function applyTimesToBooking(Booking $booking, \DateTime $pickupDateTime, \DateTime $returnDateTime, string $pickupTimeRaw, string $returnTimeRaw): void
    {
        $booking->setPickupDate($pickupDateTime);
        $booking->setReturnDate($returnDateTime);

        $pickupT = $this->parseTimeOnly($pickupTimeRaw);
        if (!$pickupT instanceof \DateTime) {
            $pickupT = \DateTime::createFromFormat('!H:i:s', $pickupDateTime->format('H:i:s'), $this->timezone) ?: null;
        }
        $returnT = $this->parseTimeOnly($returnTimeRaw);
        if (!$returnT instanceof \DateTime) {
            $returnT = \DateTime::createFromFormat('!H:i:s', $returnDateTime->format('H:i:s'), $this->timezone) ?: null;
        }
        if ($pickupT instanceof \DateTime) {
            $booking->setPickupTime($pickupT);
        }
        if ($returnT instanceof \DateTime) {
            $booking->setReturnTime($returnT);
        }
    }

    public function parseTimeOnly(string $time): ?\DateTime
    {
        $time = trim(preg_replace('/\s+/u', ' ', str_replace("\xc2\xa0", ' ', $time)));

        $formats = [
            'h:i A', 'g:i A', 'h:i a', 'g:i a', 'h:iA', 'g:iA', 'h:ia', 'g:ia',
            'H:i', 'H:i:s',
        ];

        foreach ($formats as $fmt) {
            $dt = \DateTime::createFromFormat('!'.$fmt, $time, $this->timezone);
            if ($dt instanceof \DateTime) {
                return $dt;
            }
        }

        return null;
    }

    public function validateBookingEntity(Booking $booking): ?string
    {
        if (!$booking->getPickupDate() || !$booking->getReturnDate() || !$booking->getPickupTime() || !$booking->getReturnTime()) {
            return null;
        }

        $pickupDateTime = clone $booking->getPickupDate();
        $pickupDateTime->setTimezone($this->timezone);
        $pickupDateTime->setTime(
            (int) $booking->getPickupTime()->format('H'),
            (int) $booking->getPickupTime()->format('i'),
            0
        );

        $returnDateTime = clone $booking->getReturnDate();
        $returnDateTime->setTimezone($this->timezone);
        $returnDateTime->setTime(
            (int) $booking->getReturnTime()->format('H'),
            (int) $booking->getReturnTime()->format('i'),
            0
        );

        $now = $this->now();

        if ($pickupDateTime < $now) {
            return 'Pickup date/time cannot be in the past.';
        }

        if ($returnDateTime < $now) {
            return 'Return date/time cannot be in the past.';
        }

        if ($returnDateTime < $pickupDateTime) {
            return 'Return date/time cannot be before pickup date/time.';
        }

        if (!$this->isThirtyMinuteSlot($pickupDateTime)) {
            return 'Pickup time must use 30-minute intervals (e.g. 09:00 or 09:30).';
        }

        if (!$this->isThirtyMinuteSlot($returnDateTime)) {
            return 'Return time must use 30-minute intervals (e.g. 09:00 or 09:30).';
        }

        if ($returnDateTime->getTimestamp() - $pickupDateTime->getTimestamp() < 30 * 60) {
            return 'Rental must be at least 30 minutes.';
        }

        return null;
    }

    private function now(): \DateTime
    {
        return new \DateTime('now', $this->timezone);
    }
}
