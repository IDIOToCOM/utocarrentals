<?php

namespace App\Service;

use App\Booking\BookingStatus;
use App\Entity\Booking;
use App\Entity\Login;
use App\Entity\Payment;
use App\Payment\PaymentStatus;
use App\Repository\PaymentRepository;
use Doctrine\ORM\EntityManagerInterface;

final class BookingPaymentService
{
    public function __construct(
        private readonly RentalPriceCalculator $rentalPriceCalculator,
        private readonly PaymentRepository $paymentRepository,
    ) {
    }

    public function calculateAmountDue(Booking $booking): int
    {
        $car = $booking->getCar();
        if ($car === null) {
            return 0;
        }

        $pricePerDay = $car->getPricePerDay();
        $pickup = $booking->getPickupDate();
        $return = $booking->getReturnDate();

        if ($pickup && $return) {
            return $this->rentalPriceCalculator->estimateTotal($pickup, $return, $pricePerDay);
        }

        return $this->rentalPriceCalculator->minimumTotal($pricePerDay);
    }

    public function ensurePaymentForBooking(Booking $booking, EntityManagerInterface $em, ?Login $createdBy = null): ?Payment
    {
        if ($booking->getId() === null || $booking->getCar() === null) {
            return null;
        }

        $existing = $this->paymentRepository->findOneByBookingId($booking->getId());
        if ($existing instanceof Payment) {
            $this->syncAmountDue($existing, $booking);
            $em->flush();

            return $existing;
        }

        $payment = new Payment();
        $payment->setBooking($booking);
        $payment->setCar($booking->getCar());
        $payment->setName('Payment for Booking #'.$booking->getId().' - '.($booking->getName() ?? 'Booking'));
        $payment->setStatus(PaymentStatus::PENDING);
        $payment->setAmountDue($this->calculateAmountDue($booking));
        $payment->setAmountPaid(null);
        $payment->setPaidAt(null);

        if ($createdBy !== null) {
            $payment->setCreatedBy($createdBy);
        } elseif ($booking->getCreatedBy() !== null) {
            $payment->setCreatedBy($booking->getCreatedBy());
        }

        $em->persist($payment);
        $em->flush();

        return $payment;
    }

    /**
     * @param list<Booking> $bookings
     *
     * @return array<int, Payment> booking id => payment
     */
    public function ensurePaymentsForBookings(array $bookings, EntityManagerInterface $em): array
    {
        $map = [];
        foreach ($bookings as $booking) {
            $payment = $this->ensurePaymentForBooking($booking, $em);
            if ($payment !== null && $booking->getId() !== null) {
                $map[$booking->getId()] = $payment;
            }
        }

        return $map;
    }

    public function syncAmountDue(Payment $payment, Booking $booking): void
    {
        if (PaymentStatus::isPaid($payment->getStatus())) {
            return;
        }

        $payment->setAmountDue($this->calculateAmountDue($booking));
    }

    public function recordCustomerPayment(Payment $payment, int $amountPaid): void
    {
        if ($amountPaid <= 0) {
            throw new \InvalidArgumentException('Payment amount must be greater than zero.');
        }

        $payment->setAmountPaid($amountPaid);
        $payment->setStatus(PaymentStatus::COMPLETED);
        $payment->setPaidAt(new \DateTime('now'));
    }

    /**
     * Keeps payment records when a customer cancels; marks paid balances as refunded.
     */
    public function onBookingCancelled(Booking $booking, ?Payment $payment): void
    {
        $booking->setStatus(BookingStatus::CANCELLED);

        if (!$payment instanceof Payment) {
            return;
        }

        if (PaymentStatus::isPaid($payment->getStatus())) {
            $payment->setStatus(PaymentStatus::REFUNDED);
        }
    }

    public function applyAdminStatusChange(Booking $booking, string $newStatus, ?Payment $payment): void
    {
        if ($newStatus === BookingStatus::CANCELLED) {
            $this->onBookingCancelled($booking, $payment);

            return;
        }

        if ($newStatus === BookingStatus::REFUNDED) {
            $booking->setStatus(BookingStatus::REFUNDED);
            if ($payment instanceof Payment && PaymentStatus::isPaid($payment->getStatus())) {
                $payment->setStatus(PaymentStatus::REFUNDED);
            }

            return;
        }

        $booking->setStatus($newStatus);
    }
}
