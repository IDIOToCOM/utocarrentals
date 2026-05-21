<?php

namespace App\Controller;

use App\Entity\Booking;
use App\Entity\CarInventory;
use App\Entity\Login;
use App\Car\CatalogAvailabilityQuery;
use App\Repository\CarInventoryRepository;
use App\Service\BookingConflictChecker;
use App\Service\BookingNotificationService;
use App\Service\BookingPaymentService;
use App\Service\BookingScheduleValidator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[IsGranted('ROLE_USER')]
final class SubmitBookingController extends AbstractController
{
    public function __construct(
        private readonly BookingScheduleValidator $scheduleValidator,
        private readonly CarInventoryRepository $carInventoryRepository,
        private readonly BookingConflictChecker $conflictChecker,
        private readonly BookingPaymentService $bookingPaymentService,
        private readonly BookingNotificationService $bookingNotifications,
    ) {
    }

    #[Route('/submit/booking', name: 'app_submit_booking', methods: ['GET'])]
    public function index(Request $request): Response
    {
        if ($request->query->get('booked') === '1') {
            $this->addFlash('success', 'Your booking was submitted. Status: Pending — we will review and confirm within 24 hours. You can pay from My bookings once your reservation is confirmed.');

            return $this->redirectToRoute('app_my_bookings');
        }

        $carId = $request->query->get('car');
        $selectedCar = $this->resolveCarFromId(is_scalar($carId) ? (string) $carId : null);

        if ($selectedCar === null) {
            $this->addFlash('info', 'Choose a vehicle from the catalog, then click “Book this vehicle” to continue.');

            return $this->redirectToRoute('app_car_catalog');
        }

        if (!$selectedCar->isVisibleToCustomers()) {
            $this->addFlash('error', 'That vehicle is out of service and cannot be booked. Please choose another one.');

            return $this->redirectToRoute('app_car_catalog');
        }

        $schedulePrefill = CatalogAvailabilityQuery::fromRequest($request)->toQueryParams();

        return $this->render('submit_booking/index.html.twig', [
            'bookingSubmitted' => false,
            'selected_car' => $selectedCar,
            'account_defaults' => $this->accountBookingDefaults(),
            'schedule_prefill' => $schedulePrefill,
        ]);
    }

    #[Route('/submit/booking/car/{carId}/availability', name: 'app_submit_booking_car_availability', methods: ['GET'])]
    public function carAvailability(int $carId): JsonResponse
    {
        $car = $this->carInventoryRepository->find($carId);
        if (!$car instanceof CarInventory) {
            return new JsonResponse(['error' => 'Vehicle not found'], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse($this->conflictChecker->getCalendarData($carId));
    }

    #[Route('/submit/booking/check-conflict', name: 'app_submit_booking_check_conflict', methods: ['POST'])]
    public function checkConflict(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            if (!\is_array($data)) {
                return new JsonResponse(['hasConflict' => false, 'message' => '']);
            }

            $carId = $data['carId'] ?? null;
            $excludeBookingId = isset($data['excludeBookingId']) ? (int) $data['excludeBookingId'] : null;
            $pickupDate = $data['pickupDate'] ?? null;
            $returnDate = $data['returnDate'] ?? null;
            $pickupTime = $data['pickupTime'] ?? null;
            $returnTime = $data['returnTime'] ?? null;

            if (!$carId || !$pickupDate || !$returnDate || !$pickupTime || !$returnTime) {
                return new JsonResponse(['hasConflict' => false, 'message' => '']);
            }

            $pickupTimeObj = $this->scheduleValidator->parseTimeOnly((string) $pickupTime);
            $returnTimeObj = $this->scheduleValidator->parseTimeOnly((string) $returnTime);

            if (!$pickupTimeObj || !$returnTimeObj) {
                return new JsonResponse(['hasConflict' => false, 'message' => '']);
            }

            $hasConflict = $this->conflictChecker->hasConflict(
                (int) $carId,
                new \DateTimeImmutable((string) $pickupDate),
                new \DateTimeImmutable((string) $returnDate),
                $pickupTimeObj,
                $returnTimeObj,
                $excludeBookingId > 0 ? $excludeBookingId : null,
            );

            return new JsonResponse([
                'hasConflict' => $hasConflict,
                'message' => $hasConflict ? BookingConflictChecker::MESSAGE : '',
            ]);
        } catch (\Throwable) {
            return new JsonResponse([
                'hasConflict' => true,
                'message' => BookingConflictChecker::MESSAGE,
            ]);
        }
    }

    #[Route('/submit/booking', name: 'app_submit_booking_save', methods: ['POST'])]
    public function save(Request $request, EntityManagerInterface $em): Response
    {
        $customerName = (string) $request->request->get('name');
        $phone = (string) $request->request->get('phone');
        $pickupLocation = (string) $request->request->get('pickupLocation');
        $dropoffLocation = (string) $request->request->get('dropoffLocation');
        $pickupDate = (string) $request->request->get('pickupDate');
        $returnDate = (string) $request->request->get('returnDate');
        $pickupTime = (string) $request->request->get('pickupTime');
        $returnTime = (string) $request->request->get('returnTime');
        $carIdRaw = $request->request->get('carId');

        $old = $request->request->all();
        $selectedCar = $this->resolveCarFromId(is_scalar($carIdRaw) ? (string) $carIdRaw : null);

        if ($selectedCar === null) {
            $this->addFlash('info', 'Choose a vehicle from the catalog, then click “Book this vehicle” to continue.');

            return $this->redirectToRoute('app_car_catalog');
        }

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

        if (!$selectedCar->isVisibleToCustomers()) {
            $formErrors[] = 'The selected vehicle is out of service. Please pick another vehicle.';
        }

        if ($formErrors === []) {
            $pickupTimeObj = $this->scheduleValidator->parseTimeOnly($pickupTime);
            $returnTimeObj = $this->scheduleValidator->parseTimeOnly($returnTime);

            if (!$pickupTimeObj || !$returnTimeObj) {
                $formErrors[] = 'Enter valid pickup and return times.';
            } elseif ($this->conflictChecker->hasConflict(
                $selectedCar->getId(),
                new \DateTimeImmutable($pickupDate),
                new \DateTimeImmutable($returnDate),
                $pickupTimeObj,
                $returnTimeObj,
            )) {
                $availabilityError = BookingConflictChecker::MESSAGE;
            }
        }

        if ($formErrors !== [] || $availabilityError !== null) {
            return $this->render('submit_booking/index.html.twig', [
                'errors' => $formErrors,
                'availability_error' => $availabilityError,
                'old' => $old,
                'selected_car' => $selectedCar,
                'bookingSubmitted' => false,
                'account_defaults' => $this->accountBookingDefaults(),
            ]);
        }

        $pickupDateTime = $this->scheduleValidator->combineDateAndTime($pickupDate, $pickupTime);
        $returnDateTime = $this->scheduleValidator->combineDateAndTime($returnDate, $returnTime);

        $booking = new Booking();
        $booking->setStatus(Booking::STATUS_PENDING);
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

        $booking->setCar($selectedCar);

        $user = $this->getUser();
        if ($user instanceof Login) {
            $booking->setCreatedBy($user);
        }

        $em->persist($booking);
        $em->flush();

        $createdBy = $user instanceof Login ? $user : null;
        $this->bookingPaymentService->ensurePaymentForBooking($booking, $em, $createdBy);
        $this->bookingNotifications->notifyBookingSubmitted($booking, $em);
        $em->flush();

        return $this->redirectToRoute('app_submit_booking', ['booked' => '1']);
    }

    private function resolveCarFromId(?string $carId): ?CarInventory
    {
        if ($carId === null || $carId === '') {
            return null;
        }

        $id = (int) $carId;
        if ($id <= 0) {
            return null;
        }

        $car = $this->carInventoryRepository->find($id);

        return $car instanceof CarInventory ? $car : null;
    }

    /**
     * @return array{name: string, phone: string}
     */
    private function accountBookingDefaults(): array
    {
        $user = $this->getUser();
        if (!$user instanceof Login) {
            return ['name' => '', 'phone' => ''];
        }

        $name = trim((string) ($user->getDisplayName() ?? ''));
        if ($name === '') {
            $name = (string) $user->getUsername();
        }

        return [
            'name' => $name,
            'phone' => trim((string) ($user->getPhone() ?? '')),
        ];
    }
}
