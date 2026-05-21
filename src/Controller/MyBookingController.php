<?php

namespace App\Controller;

use App\Entity\Booking;
use App\Entity\Login;
use App\Payment\PaymentStatus;
use App\Repository\BookingRepository;
use App\Repository\PaymentRepository;
use App\Service\BookingConflictChecker;
use App\Service\BookingCustomerRules;
use App\Service\BookingNotificationService;
use App\Service\BookingPaymentService;
use App\Service\BookingScheduleValidator;
use App\Service\CarCatalogImageResolver;
use App\Service\RentalPriceCalculator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
#[Route('/my/bookings')]
final class MyBookingController extends AbstractController
{
    public function __construct(
        private readonly BookingRepository $bookingRepository,
        private readonly PaymentRepository $paymentRepository,
        private readonly BookingScheduleValidator $scheduleValidator,
        private readonly BookingConflictChecker $conflictChecker,
        private readonly CarCatalogImageResolver $imageResolver,
        private readonly RentalPriceCalculator $rentalPriceCalculator,
        private readonly BookingPaymentService $bookingPaymentService,
        private readonly BookingCustomerRules $customerRules,
        private readonly BookingNotificationService $bookingNotifications,
    ) {
    }

    #[Route('', name: 'app_my_bookings', methods: ['GET'])]
    public function index(EntityManagerInterface $em): Response
    {
        if ($response = $this->redirectStaffAway()) {
            return $response;
        }

        $user = $this->requireLogin();
        $bookings = $this->bookingRepository->findForCustomer($user, true, $em);
        $paymentsByBooking = $this->bookingPaymentService->ensurePaymentsForBookings($bookings, $em);

        $payableCount = 0;
        foreach ($bookings as $booking) {
            $payment = $paymentsByBooking[$booking->getId()] ?? null;
            if ($payment !== null && $this->customerRules->canCustomerPay($booking, $payment->getStatus())) {
                ++$payableCount;
            }
        }

        return $this->render('my_bookings/index.html.twig', [
            'bookings' => $bookings,
            'paymentsByBooking' => $paymentsByBooking,
            'customerRules' => $this->customerRules,
            'payableCount' => $payableCount,
        ]);
    }

    #[Route('/pay-bulk', name: 'app_my_bookings_pay_bulk', methods: ['POST'])]
    public function payBulk(Request $request, EntityManagerInterface $em): Response
    {
        if ($response = $this->redirectStaffAway()) {
            return $response;
        }

        if (!$this->isCsrfTokenValid('my_bookings_pay_bulk', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid security token.');
        }

        $bookingIds = [];
        foreach ($request->request->all('pay_booking') as $rawId) {
            $id = (int) $rawId;
            if ($id > 0) {
                $bookingIds[] = $id;
            }
        }

        if ($bookingIds === []) {
            $this->addFlash('error', 'Select at least one booking to pay.');

            return $this->redirectToRoute('app_my_bookings');
        }

        $amounts = $request->request->all('amount');
        $paidCount = 0;
        $totalPaid = 0;
        $errors = [];

        foreach ($bookingIds as $bookingId) {
            try {
                $booking = $this->getOwnedBooking($bookingId);
            } catch (\Throwable) {
                continue;
            }

            $payment = $this->bookingPaymentService->ensurePaymentForBooking($booking, $em);
            if ($payment === null) {
                $errors[] = sprintf('Booking #%d has no payment record.', $bookingId);
                continue;
            }

            if (!$this->customerRules->canCustomerPay($booking, $payment->getStatus())) {
                $errors[] = sprintf('Booking #%d cannot be paid right now.', $bookingId);
                continue;
            }

            $amount = $this->parsePaymentAmount($amounts[$bookingId] ?? null);
            if ($amount === null) {
                $errors[] = sprintf('Enter a valid amount for booking #%d.', $bookingId);
                continue;
            }

            try {
                $this->bookingPaymentService->recordCustomerPayment($payment, $amount);
                ++$paidCount;
                $totalPaid += $amount;
            } catch (\InvalidArgumentException $e) {
                $errors[] = sprintf('Booking #%d: %s', $bookingId, $e->getMessage());
            }
        }

        if ($paidCount > 0) {
            $em->flush();
            $this->addFlash(
                'success',
                sprintf(
                    'Demo payment recorded for %d booking%s (₱%s total). Admin will see them as paid.',
                    $paidCount,
                    $paidCount === 1 ? '' : 's',
                    number_format($totalPaid, 0, '.', ','),
                ),
            );
        }

        foreach ($errors as $error) {
            $this->addFlash('error', $error);
        }

        return $this->redirectToRoute('app_my_bookings');
    }

    #[Route('/{id}/pay', name: 'app_my_booking_pay', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function pay(Request $request, int $id, EntityManagerInterface $em): Response
    {
        if ($response = $this->redirectStaffAway()) {
            return $response;
        }

        $booking = $this->getOwnedBooking($id);

        if (!$this->isCsrfTokenValid('my_booking_pay_'.$id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid security token.');
        }

        $payment = $this->bookingPaymentService->ensurePaymentForBooking($booking, $em);
        if ($payment === null) {
            $this->addFlash('error', 'No payment record is available for this booking.');

            return $this->redirectAfterPay($request, $id);
        }

        if (!$this->customerRules->canCustomerPay($booking, $payment->getStatus())) {
            $this->addFlash('info', 'This booking does not accept payment right now.');

            return $this->redirectAfterPay($request, $id);
        }

        $amount = $this->parsePaymentAmount($request->request->get('amount'));
        if ($amount === null) {
            $this->addFlash('error', 'Enter a valid whole-number payment amount.');

            return $this->redirectAfterPay($request, $id);
        }

        try {
            $this->bookingPaymentService->recordCustomerPayment($payment, $amount);
            $em->flush();
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->redirectAfterPay($request, $id);
        }

        $this->addFlash('success', 'Thank you! Your payment of ₱'.number_format($amount, 0, '.', ',').' was recorded successfully.');

        return $this->redirectAfterPay($request, $id);
    }

    #[Route('/{id}', name: 'app_my_booking_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(int $id, EntityManagerInterface $em): Response
    {
        if ($response = $this->redirectStaffAway()) {
            return $response;
        }

        $booking = $this->getOwnedBooking($id);
        $car = $booking->getCar();
        $imageUrl = $car ? $this->imageResolver->resolve($car) : null;

        $rentalDays = null;
        $estimatedTotal = null;
        $pricePerDay = $car?->getPricePerDay();

        if ($car && $booking->getPickupDate() && $booking->getReturnDate()) {
            $rentalDays = $this->rentalPriceCalculator->countRentalDays(
                $booking->getPickupDate(),
                $booking->getReturnDate(),
            );
            $estimatedTotal = $this->rentalPriceCalculator->estimateTotal(
                $booking->getPickupDate(),
                $booking->getReturnDate(),
                $pricePerDay,
            );
        }

        $payment = $this->bookingPaymentService->ensurePaymentForBooking($booking, $em);

        return $this->render('my_bookings/show.html.twig', [
            'booking' => $booking,
            'car' => $car,
            'imageUrl' => $imageUrl,
            'rentalDays' => $rentalDays,
            'estimatedTotal' => $estimatedTotal,
            'pricePerDay' => $pricePerDay,
            'payment' => $payment,
            'canPay' => $payment !== null && $this->customerRules->canCustomerPay($booking, $payment->getStatus()),
            'canEdit' => $this->customerRules->canCustomerEdit($booking),
            'canCancel' => $this->customerRules->canCustomerCancel($booking),
            'editBlockReason' => $this->customerRules->getEditBlockReason($booking),
            'cancelBlockReason' => $this->customerRules->getCancelBlockReason($booking),
            'customerRules' => $this->customerRules,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_my_booking_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, int $id, EntityManagerInterface $em): Response
    {
        if ($response = $this->redirectStaffAway()) {
            return $response;
        }

        $booking = $this->getOwnedBooking($id);
        $car = $booking->getCar();

        if ($car === null) {
            $this->addFlash('error', 'This booking has no vehicle assigned and cannot be edited.');

            return $this->redirectToRoute('app_my_bookings');
        }

        $editBlock = $this->customerRules->getEditBlockReason($booking);
        if ($editBlock !== null) {
            $this->addFlash('info', $editBlock);

            return $this->redirectToRoute('app_my_booking_show', ['id' => $booking->getId()]);
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('my_booking_edit_'.$id, (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException('Invalid security token.');
            }

            $customerName = (string) $request->request->get('name');
            $phone = (string) $request->request->get('phone');
            $pickupLocation = (string) $request->request->get('pickupLocation');
            $dropoffLocation = (string) $request->request->get('dropoffLocation');
            $pickupDate = (string) $request->request->get('pickupDate');
            $returnDate = (string) $request->request->get('returnDate');
            $pickupTime = (string) $request->request->get('pickupTime');
            $returnTime = (string) $request->request->get('returnTime');
            $old = $request->request->all();

            $formErrors = array_values($this->scheduleValidator->validateSubmission(
                $customerName,
                $phone,
                $pickupLocation,
                $dropoffLocation,
                $pickupDate,
                $returnDate,
                $pickupTime,
                $returnTime,
            ));
            $availabilityError = null;

            if (!$car->isVisibleToCustomers()) {
                $formErrors[] = 'This vehicle is out of service and cannot be booked.';
            }

            if ($formErrors === []) {
                $pickupTimeObj = $this->scheduleValidator->parseTimeOnly($pickupTime);
                $returnTimeObj = $this->scheduleValidator->parseTimeOnly($returnTime);

                if (!$pickupTimeObj || !$returnTimeObj) {
                    $formErrors[] = 'Enter valid pickup and return times.';
                } elseif ($this->conflictChecker->hasConflict(
                    $car->getId(),
                    new \DateTimeImmutable($pickupDate),
                    new \DateTimeImmutable($returnDate),
                    $pickupTimeObj,
                    $returnTimeObj,
                    $booking->getId(),
                )) {
                    $availabilityError = BookingConflictChecker::MESSAGE;
                }
            }

            if ($formErrors !== [] || $availabilityError !== null) {
                return $this->render('submit_booking/index.html.twig', [
                    'is_edit_mode' => true,
                    'booking' => $booking,
                    'selected_car' => $car,
                    'errors' => $formErrors,
                    'availability_error' => $availabilityError,
                    'old' => $old,
                    'bookingSubmitted' => false,
                ]);
            }

            $pickupDateTime = $this->scheduleValidator->combineDateAndTime($pickupDate, $pickupTime);
            $returnDateTime = $this->scheduleValidator->combineDateAndTime($returnDate, $returnTime);

            $booking->setName(trim($customerName));
            $booking->setPhone(trim($phone));
            $booking->setPickupLocation($pickupLocation);
            $booking->setDropoffLocation($dropoffLocation);
            $this->scheduleValidator->applyTimesToBooking(
                $booking,
                $pickupDateTime,
                $returnDateTime,
                $pickupTime,
                $returnTime,
            );

            $em->flush();

            $payment = $this->paymentRepository->findOneByBookingId((int) $booking->getId());
            if ($payment !== null) {
                $this->bookingPaymentService->syncAmountDue($payment, $booking);
                $em->flush();
            }

            $this->addFlash('success', 'Your booking was updated.');

            return $this->redirectToRoute('app_my_booking_show', ['id' => $booking->getId()]);
        }

        return $this->render('submit_booking/index.html.twig', [
            'is_edit_mode' => true,
            'booking' => $booking,
            'selected_car' => $car,
            'errors' => [],
            'availability_error' => null,
            'old' => null,
            'bookingSubmitted' => false,
        ]);
    }

    #[Route('/{id}/cancel', name: 'app_my_booking_cancel', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function cancel(Request $request, int $id, EntityManagerInterface $em): Response
    {
        if ($response = $this->redirectStaffAway()) {
            return $response;
        }

        $booking = $this->getOwnedBooking($id);

        if (!$this->isCsrfTokenValid('my_booking_cancel_'.$id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid security token.');
        }

        return $this->performCustomerCancel($booking, $em);
    }

    /** @deprecated Use cancel; kept for old bookmarks/forms. */
    #[Route('/{id}/delete', name: 'app_my_booking_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, int $id, EntityManagerInterface $em): Response
    {
        if ($response = $this->redirectStaffAway()) {
            return $response;
        }

        $booking = $this->getOwnedBooking($id);

        if (!$this->isCsrfTokenValid('my_booking_delete_'.$id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid security token.');
        }

        return $this->performCustomerCancel($booking, $em);
    }

    private function performCustomerCancel(Booking $booking, EntityManagerInterface $em): Response
    {
        $cancelBlock = $this->customerRules->getCancelBlockReason($booking);
        if ($cancelBlock !== null) {
            $this->addFlash('info', $cancelBlock);

            return $this->redirectToRoute('app_my_booking_show', ['id' => $booking->getId()]);
        }

        $payment = $this->paymentRepository->findOneByBookingId((int) $booking->getId());
        $wasPaid = $payment !== null && PaymentStatus::isPaid($payment->getStatus());

        $this->bookingPaymentService->onBookingCancelled($booking, $payment);
        $this->bookingNotifications->notifyCustomerCancelled($booking, $em);
        $em->flush();

        $message = 'Your booking was cancelled. The vehicle is no longer reserved for those dates.';
        if ($wasPaid) {
            $message .= ' Your payment was marked for refund—our team will follow up if needed.';
        }

        $this->addFlash('success', $message);

        return $this->redirectToRoute('app_my_booking_show', ['id' => $booking->getId()]);
    }

    private function requireLogin(): Login
    {
        $user = $this->getUser();
        if (!$user instanceof Login) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }

    private function getOwnedBooking(int $id): Booking
    {
        $user = $this->requireLogin();
        $booking = $this->bookingRepository->findOneForCustomer($id, $user);

        if (!$booking instanceof Booking) {
            throw $this->createNotFoundException('Booking not found.');
        }

        return $booking;
    }

    private function redirectStaffAway(): ?Response
    {
        if ($this->isGranted('ROLE_ADMIN') || $this->isGranted('ROLE_STAFF')) {
            return $this->redirectToRoute('app_booking_index');
        }

        return null;
    }

    private function redirectAfterPay(Request $request, int $bookingId): Response
    {
        if ($request->request->get('_redirect') === 'list') {
            return $this->redirectToRoute('app_my_bookings');
        }

        return $this->redirectToRoute('app_my_booking_show', ['id' => $bookingId]);
    }

    private function parsePaymentAmount(mixed $raw): ?int
    {
        $raw = trim((string) $raw);
        if ($raw === '' || !ctype_digit($raw)) {
            return null;
        }

        $amount = (int) $raw;

        return $amount > 0 ? $amount : null;
    }
}
