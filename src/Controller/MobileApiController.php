<?php

declare(strict_types=1);

namespace App\Controller;

use App\Car\CarReviewSummary;
use App\Entity\AppNotification;
use App\Entity\Booking;
use App\Entity\CarInventory;
use App\Entity\Login;
use App\Entity\Payment;
use App\Repository\AppNotificationRepository;
use App\MobileApi\MobileApiEnvelope;
use App\Payment\PaymentStatus;
use App\Repository\BookingRepository;
use App\Repository\CarFavoriteRepository;
use App\Repository\CarInventoryRepository;
use App\Entity\CarReview;
use App\Service\CarFavoriteService;
use App\Service\BookingConflictChecker;
use App\Service\BookingCustomerRules;
use App\Service\BookingNotificationService;
use App\Service\BookingPaymentService;
use App\Service\BookingScheduleValidator;
use App\Service\CarPhotoUploadService;
use App\Service\CarReviewService;
use App\Service\DeviceTokenService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * JSON API for mobile apps (standardized envelope).
 */
#[Route('/api/mobile/v1')]
final class MobileApiController extends AbstractController
{
    public function __construct(
        private readonly CarInventoryRepository $carInventoryRepository,
        private readonly BookingRepository $bookingRepository,
        private readonly AppNotificationRepository $notificationRepository,
        private readonly BookingScheduleValidator $scheduleValidator,
        private readonly BookingConflictChecker $conflictChecker,
        private readonly BookingPaymentService $bookingPaymentService,
        private readonly BookingCustomerRules $customerRules,
        private readonly BookingNotificationService $bookingNotifications,
        private readonly CarPhotoUploadService $photoUploadService,
        private readonly CarReviewService $carReviewService,
        private readonly CarFavoriteService $favoriteService,
        private readonly CarFavoriteRepository $favoriteRepository,
        private readonly DeviceTokenService $deviceTokenService,
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('/health', name: 'api_mobile_v1_health', methods: ['GET'])]
    public function health(): JsonResponse
    {
        return MobileApiEnvelope::ok([
            'status' => 'ok',
            'service' => 'uto-mobility',
        ]);
    }

    /**
     * List fleet vehicles. Optional query: ?status=Available (exact match to stored status).
     */
    #[Route('/cars', name: 'api_mobile_v1_cars_list', methods: ['GET'])]
    public function listCars(Request $request): JsonResponse
    {
        $status = $request->query->get('status');
        if (\is_string($status) && $status !== '') {
            $qb = $this->carInventoryRepository->createQueryBuilder('c')
                ->andWhere('c.Status = :status')
                ->setParameter('status', $status)
                ->orderBy('c.id', 'ASC');
            $cars = $qb->getQuery()->getResult();
        } else {
            $cars = $this->carInventoryRepository->findForCustomerCatalog(null);
        }

        /** @var list<CarInventory> $cars */
        $carIds = array_values(array_filter(array_map(
            static fn (CarInventory $c): ?int => $c->getId(),
            $cars,
        )));
        $summaries = $this->carReviewService->getSummariesForCarIds($carIds);
        $bookingCounts = $this->bookingRepository->countActiveBookingsByCarIds($carIds);
        $items = array_map(
            function (CarInventory $car) use ($summaries, $bookingCounts): array {
                $id = $car->getId();
                $serialized = $this->serializeCar(
                    $car,
                    $id !== null ? ($summaries[$id] ?? null) : null,
                );
                if ($id !== null) {
                    $serialized['bookingCount'] = $bookingCounts[$id] ?? 0;
                }

                return $serialized;
            },
            $cars,
        );

        $popularCars = array_values(array_filter(
            $items,
            static fn (array $car): bool => ($car['reviewCount'] ?? 0) >= 1
                && (float) ($car['averageRating'] ?? 0) >= 4.0,
        ));
        usort(
            $popularCars,
            static function (array $a, array $b): int {
                $byRating = (float) ($b['averageRating'] ?? 0) <=> (float) ($a['averageRating'] ?? 0);
                if ($byRating !== 0) {
                    return $byRating;
                }

                return ($b['reviewCount'] ?? 0) <=> ($a['reviewCount'] ?? 0);
            },
        );
        $popularCars = \array_slice($popularCars, 0, 8);

        return MobileApiEnvelope::ok(
            ['cars' => $items, 'popularCars' => $popularCars],
            ['count' => \count($items)],
        );
    }

    #[Route('/cars/{id}', name: 'api_mobile_v1_car_get', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function getCar(int $id): JsonResponse
    {
        $car = $this->carInventoryRepository->findAvailableForCustomer($id);
        if (!$car instanceof CarInventory) {
            return MobileApiEnvelope::fail(
                'CAR_NOT_FOUND',
                \sprintf('No car with id %d.', $id),
                Response::HTTP_NOT_FOUND,
            );
        }

        $summary = $this->carReviewService->getSummaryForCar($id);
        $serialized = $this->serializeCar($car, $summary);
        $bookingCounts = $this->bookingRepository->countActiveBookingsByCarIds([$id]);
        $serialized['bookingCount'] = $bookingCounts[$id] ?? 0;

        return MobileApiEnvelope::ok(['car' => $serialized]);
    }

    #[Route('/cars/{id}/reviews', name: 'api_mobile_v1_car_reviews', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function getCarReviews(int $id): JsonResponse
    {
        $car = $this->carInventoryRepository->find($id);
        if (!$car instanceof CarInventory) {
            return MobileApiEnvelope::fail(
                'CAR_NOT_FOUND',
                \sprintf('No car with id %d.', $id),
                Response::HTTP_NOT_FOUND,
            );
        }

        $summary = $this->carReviewService->getSummaryForCar($id);
        $reviews = $this->carReviewService->getReviewsForCar($id);

        $userReview = null;
        $canSubmitReview = false;
        $user = $this->getUser();
        if ($user instanceof Login && $this->isCustomerUser($user)) {
            $existing = $this->carReviewService->findUserReview($user, $id);
            if ($existing instanceof CarReview) {
                $userReview = $this->serializeReview($existing);
            }
            $canSubmitReview = $this->carReviewService->canCustomerSubmitOrEdit($user, $id);
        }

        return MobileApiEnvelope::ok([
            'summary' => [
                'reviewCount' => $summary->reviewCount,
                'averageRating' => $summary->averageRating,
            ],
            'reviews' => array_map(fn (CarReview $r) => $this->serializeReview($r), $reviews),
            'userReview' => $userReview,
            'canSubmitReview' => $canSubmitReview,
        ]);
    }

    #[Route('/cars/{id}/reviews', name: 'api_mobile_v1_car_reviews_submit', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function submitCarReview(Request $request, int $id): JsonResponse
    {
        $user = $this->requireLoginUser();
        if (!$this->isCustomerUser($user)) {
            return MobileApiEnvelope::fail(
                'FORBIDDEN',
                'Sign in with a customer account to leave a review.',
                Response::HTTP_FORBIDDEN,
            );
        }

        $data = $this->parseJsonBody($request) ?? [];
        $rating = isset($data['rating']) ? (int) $data['rating'] : 0;
        $comment = isset($data['comment']) && \is_string($data['comment']) ? $data['comment'] : null;

        try {
            $this->carReviewService->submitReview($user, $id, $rating, $comment, $this->em);
        } catch (\InvalidArgumentException $e) {
            return MobileApiEnvelope::fail(
                'VALIDATION_ERROR',
                $e->getMessage(),
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        return $this->getCarReviews($id);
    }

    #[Route('/bookings', name: 'api_mobile_v1_bookings_list', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function listBookings(): JsonResponse
    {
        $user = $this->requireLoginUser();

        $bookings = $this->bookingRepository->findForCustomer($user, false);
        $paymentsByBooking = $this->bookingPaymentService->ensurePaymentsForBookings($bookings, $this->em);
        $items = array_map(
            function (Booking $b) use ($paymentsByBooking): array {
                $payment = $b->getId() !== null ? ($paymentsByBooking[$b->getId()] ?? null) : null;

                return $this->serializeBooking($b, $payment);
            },
            $bookings,
        );

        return MobileApiEnvelope::ok(
            ['bookings' => $items],
            ['count' => \count($items)],
        );
    }

    #[Route('/bookings', name: 'api_mobile_v1_bookings_create', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function createBooking(Request $request): JsonResponse
    {
        $user = $this->requireLoginUser();
        $data = $this->parseJsonBody($request);
        if ($data === null) {
            return MobileApiEnvelope::fail(
                'INVALID_JSON',
                'Request body must be valid JSON.',
                Response::HTTP_BAD_REQUEST,
            );
        }

        $carId = isset($data['carId']) ? (int) $data['carId'] : 0;
        $customerName = (string) ($data['name'] ?? '');
        $phone = (string) ($data['phone'] ?? '');
        $pickupLocation = (string) ($data['pickupLocation'] ?? '');
        $dropoffLocation = (string) ($data['dropoffLocation'] ?? '');
        $pickupDate = (string) ($data['pickupDate'] ?? '');
        $returnDate = (string) ($data['returnDate'] ?? '');
        $pickupTime = (string) ($data['pickupTime'] ?? '');
        $returnTime = (string) ($data['returnTime'] ?? '');

        $formErrors = $this->scheduleValidator->validateSubmission(
            $customerName,
            $phone,
            $pickupLocation,
            $dropoffLocation,
            $pickupDate,
            $returnDate,
            $pickupTime,
            $returnTime,
        );

        if ($formErrors !== []) {
            return MobileApiEnvelope::fail(
                'VALIDATION_ERROR',
                'Please fix the highlighted fields.',
                Response::HTTP_UNPROCESSABLE_ENTITY,
                null,
                ['fields' => $formErrors],
            );
        }

        $car = $this->carInventoryRepository->find($carId);
        if (!$car instanceof CarInventory) {
            return MobileApiEnvelope::fail(
                'CAR_NOT_FOUND',
                \sprintf('No car with id %d.', $carId),
                Response::HTTP_NOT_FOUND,
            );
        }

        if (!$car->isVisibleToCustomers()) {
            return MobileApiEnvelope::fail(
                'CAR_UNAVAILABLE',
                'The selected vehicle is out of service. Please pick another vehicle.',
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $pickupTimeObj = $this->scheduleValidator->parseTimeOnly($pickupTime);
        $returnTimeObj = $this->scheduleValidator->parseTimeOnly($returnTime);
        if (!$pickupTimeObj || !$returnTimeObj) {
            return MobileApiEnvelope::fail(
                'VALIDATION_ERROR',
                'Enter valid pickup and return times.',
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        if ($this->conflictChecker->hasConflict(
            $car->getId(),
            new \DateTimeImmutable($pickupDate),
            new \DateTimeImmutable($returnDate),
            $pickupTimeObj,
            $returnTimeObj,
        )) {
            return MobileApiEnvelope::fail(
                'BOOKING_CONFLICT',
                BookingConflictChecker::MESSAGE,
                Response::HTTP_CONFLICT,
            );
        }

        $pickupDateTime = $this->scheduleValidator->combineDateAndTime($pickupDate, $pickupTime);
        $returnDateTime = $this->scheduleValidator->combineDateAndTime($returnDate, $returnTime);
        if (!$pickupDateTime || !$returnDateTime) {
            return MobileApiEnvelope::fail(
                'VALIDATION_ERROR',
                'Enter valid pickup and return schedule.',
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

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
        $booking->setCar($car);
        $booking->setCreatedBy($user);

        $this->em->persist($booking);
        $this->em->flush();

        $this->bookingPaymentService->ensurePaymentForBooking($booking, $this->em, $user);
        $this->bookingNotifications->notifyBookingSubmitted($booking, $this->em);
        $this->em->flush();

        $payment = $this->bookingPaymentService->ensurePaymentForBooking($booking, $this->em, $user);

        return MobileApiEnvelope::ok(
            ['booking' => $this->serializeBooking($booking, $payment)],
            [],
        );
    }

    #[Route('/bookings/{id}/pay', name: 'api_mobile_v1_booking_pay', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function payBooking(Request $request, int $id): JsonResponse
    {
        $user = $this->requireLoginUser();
        $booking = $this->bookingRepository->findOneForCustomer($id, $user);
        if (!$booking instanceof Booking) {
            return MobileApiEnvelope::fail(
                'BOOKING_NOT_FOUND',
                \sprintf('No booking with id %d.', $id),
                Response::HTTP_NOT_FOUND,
            );
        }

        $payment = $this->bookingPaymentService->ensurePaymentForBooking($booking, $this->em);
        if (!$payment instanceof Payment) {
            return MobileApiEnvelope::fail(
                'PAYMENT_NOT_AVAILABLE',
                'No payment record is available for this booking.',
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        if (!$this->customerRules->canCustomerPay($booking, $payment->getStatus())) {
            return MobileApiEnvelope::fail(
                'PAYMENT_NOT_ALLOWED',
                'This booking does not accept payment right now.',
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $data = $this->parseJsonBody($request) ?? [];
        $amount = isset($data['amount']) ? $this->parsePaymentAmount($data['amount']) : null;
        if ($amount === null) {
            $amountDue = $payment->getAmountDue();
            $amount = \is_int($amountDue) && $amountDue > 0 ? $amountDue : null;
        }

        if ($amount === null) {
            return MobileApiEnvelope::fail(
                'VALIDATION_ERROR',
                'Enter a valid whole-number payment amount.',
                Response::HTTP_UNPROCESSABLE_ENTITY,
                null,
                ['fields' => ['amount' => 'Enter a valid whole-number payment amount.']],
            );
        }

        try {
            $this->bookingPaymentService->recordCustomerPayment($payment, $amount);
            $this->em->flush();
        } catch (\InvalidArgumentException $e) {
            return MobileApiEnvelope::fail(
                'PAYMENT_FAILED',
                $e->getMessage(),
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        return MobileApiEnvelope::ok([
            'booking' => $this->serializeBooking($booking, $payment),
            'message' => 'Thank you! Your payment of ₱'.number_format($amount, 0, '.', ',').' was recorded successfully.',
        ]);
    }

    #[Route('/bookings/{id}/cancel', name: 'api_mobile_v1_booking_cancel', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function cancelBooking(int $id): JsonResponse
    {
        $user = $this->requireLoginUser();
        $booking = $this->bookingRepository->findOneForCustomer($id, $user);
        if (!$booking instanceof Booking) {
            return MobileApiEnvelope::fail(
                'BOOKING_NOT_FOUND',
                \sprintf('No booking with id %d.', $id),
                Response::HTTP_NOT_FOUND,
            );
        }

        $cancelBlock = $this->customerRules->getCancelBlockReason($booking);
        if ($cancelBlock !== null) {
            return MobileApiEnvelope::fail(
                'CANCEL_NOT_ALLOWED',
                $cancelBlock,
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $payment = $this->bookingPaymentService->ensurePaymentForBooking($booking, $this->em);
        $wasPaid = $payment instanceof Payment && PaymentStatus::isPaid($payment->getStatus());

        $this->bookingPaymentService->onBookingCancelled($booking, $payment);
        $this->bookingNotifications->notifyCustomerCancelled($booking, $this->em);
        $this->em->flush();

        $message = 'Your booking was cancelled. The vehicle is no longer reserved for those dates.';
        if ($wasPaid) {
            $message .= ' Your payment was marked for refund—our team will follow up if needed.';
        }

        return MobileApiEnvelope::ok([
            'booking' => $this->serializeBooking($booking, $payment),
            'message' => $message,
        ]);
    }

    #[Route('/favorites', name: 'api_mobile_v1_favorites_list', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function listFavorites(): JsonResponse
    {
        $user = $this->requireLoginUser();
        $favorites = $this->favoriteRepository->findForUserOrdered($user);
        $carIds = [];
        foreach ($favorites as $favorite) {
            $car = $favorite->getCar();
            if ($car?->getId() !== null) {
                $carIds[] = $car->getId();
            }
        }
        $summaries = $this->carReviewService->getSummariesForCarIds($carIds);
        $cars = [];
        foreach ($favorites as $favorite) {
            $car = $favorite->getCar();
            if (!$car instanceof CarInventory || $car->getId() === null) {
                continue;
            }
            $id = $car->getId();
            $cars[] = $this->serializeCar($car, $summaries[$id] ?? null);
        }

        return MobileApiEnvelope::ok([
            'cars' => $cars,
            'favoriteIds' => $this->favoriteService->getFavoriteCarIds($user),
        ], ['count' => \count($cars)]);
    }

    #[Route('/favorites/{id}/toggle', name: 'api_mobile_v1_favorites_toggle', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function toggleFavorite(int $id): JsonResponse
    {
        $user = $this->requireLoginUser();

        try {
            $action = $this->favoriteService->toggle($user, $id, $this->em);
        } catch (\InvalidArgumentException $e) {
            return MobileApiEnvelope::fail(
                'CAR_NOT_AVAILABLE',
                $e->getMessage(),
                Response::HTTP_BAD_REQUEST,
            );
        }

        return MobileApiEnvelope::ok([
            'favorited' => $action === 'added',
            'favoriteIds' => $this->favoriteService->getFavoriteCarIds($user),
        ]);
    }

    #[Route('/device-tokens', name: 'api_mobile_v1_device_tokens_register', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function registerDeviceToken(Request $request): JsonResponse
    {
        $data = $this->parseJsonBody($request);
        if ($data === null) {
            return MobileApiEnvelope::fail('INVALID_JSON', 'Request body must be JSON.', Response::HTTP_BAD_REQUEST);
        }

        $fcmToken = $data['fcmToken'] ?? $data['fcm_token'] ?? null;
        $platform = $data['platform'] ?? 'android';

        if (!\is_string($fcmToken) || trim($fcmToken) === '') {
            return MobileApiEnvelope::fail(
                'VALIDATION_ERROR',
                'fcmToken is required.',
                Response::HTTP_BAD_REQUEST,
                null,
                ['fields' => ['fcmToken' => 'FCM token is required.']],
            );
        }

        if (!\is_string($platform)) {
            $platform = 'android';
        }

        try {
            $this->deviceTokenService->register(
                $this->requireLoginUser(),
                $fcmToken,
                $platform,
            );
        } catch (\InvalidArgumentException $e) {
            return MobileApiEnvelope::fail(
                'VALIDATION_ERROR',
                $e->getMessage(),
                Response::HTTP_BAD_REQUEST,
            );
        }

        return MobileApiEnvelope::ok(['registered' => true]);
    }

    #[Route('/device-tokens', name: 'api_mobile_v1_device_tokens_remove', methods: ['DELETE'])]
    #[IsGranted('ROLE_USER')]
    public function removeDeviceToken(Request $request): JsonResponse
    {
        $data = $this->parseJsonBody($request);
        if ($data === null) {
            return MobileApiEnvelope::fail('INVALID_JSON', 'Request body must be JSON.', Response::HTTP_BAD_REQUEST);
        }

        $fcmToken = $data['fcmToken'] ?? $data['fcm_token'] ?? null;
        if (!\is_string($fcmToken) || trim($fcmToken) === '') {
            return MobileApiEnvelope::fail(
                'VALIDATION_ERROR',
                'fcmToken is required.',
                Response::HTTP_BAD_REQUEST,
            );
        }

        try {
            $this->deviceTokenService->remove($this->requireLoginUser(), $fcmToken);
        } catch (\InvalidArgumentException $e) {
            return MobileApiEnvelope::fail(
                'VALIDATION_ERROR',
                $e->getMessage(),
                Response::HTTP_BAD_REQUEST,
            );
        }

        return MobileApiEnvelope::ok(['removed' => true]);
    }

    #[Route('/notifications', name: 'api_mobile_v1_notifications_list', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function listNotifications(): JsonResponse
    {
        $user = $this->requireLoginUser();
        $loginId = (int) $user->getId();
        $items = array_map(
            fn (AppNotification $n) => $this->serializeNotification($n),
            $this->notificationRepository->findAllForUser($loginId),
        );

        return MobileApiEnvelope::ok(
            [
                'notifications' => $items,
                'unreadCount' => $this->notificationRepository->countUnreadForUser($loginId),
            ],
            ['count' => \count($items)],
        );
    }

    #[Route('/notifications/read-all', name: 'api_mobile_v1_notifications_read_all', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function markAllNotificationsRead(): JsonResponse
    {
        $user = $this->requireLoginUser();
        $loginId = (int) $user->getId();
        $this->notificationRepository->markAllReadForUser($loginId);
        $this->em->flush();

        return MobileApiEnvelope::ok([
            'unreadCount' => 0,
        ]);
    }

    #[Route('/notifications/{id}/read', name: 'api_mobile_v1_notification_read', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function markNotificationRead(int $id): JsonResponse
    {
        $user = $this->requireLoginUser();
        $loginId = (int) $user->getId();
        $notification = $this->notificationRepository->findOneForUser($id, $loginId);
        if (!$notification instanceof AppNotification) {
            return MobileApiEnvelope::fail(
                'NOTIFICATION_NOT_FOUND',
                \sprintf('No notification with id %d.', $id),
                Response::HTTP_NOT_FOUND,
            );
        }

        if (!$notification->isRead()) {
            $notification->markRead();
            $this->em->flush();
        }

        return MobileApiEnvelope::ok([
            'notification' => $this->serializeNotification($notification),
            'unreadCount' => $this->notificationRepository->countUnreadForUser($loginId),
        ]);
    }

    #[Route('/bookings/check-conflict', name: 'api_mobile_v1_bookings_check_conflict', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function checkBookingConflict(Request $request): JsonResponse
    {
        $data = $this->parseJsonBody($request);
        if ($data === null) {
            return MobileApiEnvelope::ok(['hasConflict' => false, 'message' => '']);
        }

        $carId = $data['carId'] ?? null;
        $excludeBookingId = isset($data['excludeBookingId']) ? (int) $data['excludeBookingId'] : null;
        $pickupDate = $data['pickupDate'] ?? null;
        $returnDate = $data['returnDate'] ?? null;
        $pickupTime = $data['pickupTime'] ?? null;
        $returnTime = $data['returnTime'] ?? null;

        if (!$carId || !$pickupDate || !$returnDate || !$pickupTime || !$returnTime) {
            return MobileApiEnvelope::ok(['hasConflict' => false, 'message' => '']);
        }

        try {
            $pickupTimeObj = $this->scheduleValidator->parseTimeOnly((string) $pickupTime);
            $returnTimeObj = $this->scheduleValidator->parseTimeOnly((string) $returnTime);

            if (!$pickupTimeObj || !$returnTimeObj) {
                return MobileApiEnvelope::ok(['hasConflict' => false, 'message' => '']);
            }

            $hasConflict = $this->conflictChecker->hasConflict(
                (int) $carId,
                new \DateTimeImmutable((string) $pickupDate),
                new \DateTimeImmutable((string) $returnDate),
                $pickupTimeObj,
                $returnTimeObj,
                $excludeBookingId > 0 ? $excludeBookingId : null,
            );

            return MobileApiEnvelope::ok([
                'hasConflict' => $hasConflict,
                'message' => $hasConflict ? BookingConflictChecker::MESSAGE : '',
            ]);
        } catch (\Throwable) {
            return MobileApiEnvelope::ok([
                'hasConflict' => true,
                'message' => BookingConflictChecker::MESSAGE,
            ]);
        }
    }

    private function requireLoginUser(): Login
    {
        $user = $this->getUser();
        if (!$user instanceof Login) {
            throw $this->createAccessDeniedException('Authentication required.');
        }

        return $user;
    }

    private function isCustomerUser(Login $user): bool
    {
        $roles = $user->getRoles();

        return !\in_array('ROLE_ADMIN', $roles, true) && !\in_array('ROLE_STAFF', $roles, true);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeReview(CarReview $review): array
    {
        return [
            'id' => $review->getId(),
            'rating' => $review->getRating(),
            'comment' => $review->getComment(),
            'authorDisplayName' => $review->getAuthorDisplayName(),
            'createdAt' => $review->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function parseJsonBody(Request $request): ?array
    {
        $data = json_decode($request->getContent(), true);

        return \is_array($data) ? $data : null;
    }

    /**
     * @return array<string, int|string|null|array<string, int|string|null>>
     */
    private function serializeCar(CarInventory $car, ?CarReviewSummary $reviewSummary = null): array
    {
        return [
            'id' => $car->getId(),
            'brand' => $car->getBrand(),
            'model' => $car->getModel(),
            'type' => $car->getType(),
            'pricePerDay' => $car->getPricePerDay(),
            'status' => $car->getStatus(),
            'imageUrl' => $this->photoUploadService->resolvePublicUrl($car),
            'reviewCount' => $reviewSummary?->reviewCount ?? 0,
            'averageRating' => $reviewSummary?->averageRating,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeBooking(Booking $booking, ?Payment $payment = null): array
    {
        $car = $booking->getCar();
        $canPay = $payment instanceof Payment
            && $this->customerRules->canCustomerPay($booking, $payment->getStatus());

        return [
            'id' => $booking->getId(),
            'status' => $booking->getStatus(),
            'name' => $booking->getName(),
            'phone' => $booking->getPhone(),
            'pickupDate' => $booking->getPickupDate()?->format('Y-m-d'),
            'returnDate' => $booking->getReturnDate()?->format('Y-m-d'),
            'pickupTime' => $booking->getPickupTime()?->format('H:i:s'),
            'returnTime' => $booking->getReturnTime()?->format('H:i:s'),
            'pickupLocation' => $booking->getPickupLocation(),
            'dropoffLocation' => $booking->getDropoffLocation(),
            'car' => $car instanceof CarInventory ? [
                'id' => $car->getId(),
                'brand' => $car->getBrand(),
                'model' => $car->getModel(),
            ] : null,
            'createdBy' => $booking->getCreatedBy()?->getUserIdentifier(),
            'paymentStatus' => $payment?->getStatus(),
            'amountDue' => $payment?->getAmountDue(),
            'amountPaid' => $payment?->getAmountPaid(),
            'canPay' => $canPay,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeNotification(AppNotification $notification): array
    {
        $booking = $notification->getBooking();
        $params = $notification->getLinkParams() ?? [];

        return [
            'id' => $notification->getId(),
            'type' => $notification->getType(),
            'title' => $notification->getTitle(),
            'body' => $notification->getBody(),
            'isRead' => $notification->isRead(),
            'readAt' => $notification->getReadAt()?->format(\DateTimeInterface::ATOM),
            'createdAt' => $notification->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'bookingId' => $booking?->getId(),
            'linkRoute' => $notification->getLinkRoute(),
            'linkParams' => $params,
        ];
    }

    private function parsePaymentAmount(mixed $raw): ?int
    {
        if (\is_int($raw)) {
            return $raw > 0 ? $raw : null;
        }

        $raw = trim((string) $raw);
        if ($raw === '' || !ctype_digit($raw)) {
            return null;
        }

        $amount = (int) $raw;

        return $amount > 0 ? $amount : null;
    }
}
