<?php

namespace App\Controller;

use App\Booking\BookingStatus;
use App\Entity\Booking;
use App\Entity\Login;
use App\Entity\ActivityLog;
use App\Entity\Payment;
use App\Form\BookingType;
use App\Payment\PaymentStatus;
use App\Repository\BookingRepository;
use App\Repository\LoginRepository;
use App\Repository\PaymentRepository;
use App\Service\BookingConflictChecker;
use App\Service\BookingNotificationService;
use App\Service\BookingPaymentService;
use App\Service\BookingScheduleValidator;
use App\Service\RentalPriceCalculator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/booking')]
final class BookingController extends AbstractController
{
    public function __construct(
        private readonly BookingScheduleValidator $scheduleValidator,
        private readonly BookingConflictChecker $conflictChecker,
        private readonly RentalPriceCalculator $rentalPriceCalculator,
        private readonly BookingPaymentService $bookingPaymentService,
        private readonly BookingNotificationService $bookingNotifications,
    ) {
    }

    #[Route(name: 'app_booking_index', methods: ['GET'])]
    public function index(BookingRepository $bookingRepository, PaymentRepository $paymentRepository): Response
    {
        // Load relationships to avoid lazy loading issues
        $bookings = $bookingRepository->createQueryBuilder('b')
            ->leftJoin('b.car', 'car')
            ->addSelect('car')
            ->leftJoin('b.user', 'user')
            ->addSelect('user')
            ->leftJoin('b.createdBy', 'creator')
            ->addSelect('creator')
            ->getQuery()
            ->getResult();

        $bookingIds = array_values(array_filter(array_map(
            static fn (Booking $b): ?int => $b->getId(),
            $bookings,
        )));
        $paymentsByBooking = $paymentRepository->findMapByBookingIds($bookingIds);

        return $this->render('booking/index.html.twig', [
            'bookings' => $bookings,
            'paymentsByBooking' => $paymentsByBooking,
        ]);
    }

    #[Route('/poll', name: 'app_booking_poll', methods: ['GET'])]
    public function poll(BookingRepository $bookingRepository, PaymentRepository $paymentRepository): JsonResponse
    {
        $bookings = $bookingRepository->createQueryBuilder('b')
            ->leftJoin('b.car', 'car')
            ->addSelect('car')
            ->leftJoin('b.user', 'user')
            ->addSelect('user')
            ->leftJoin('b.createdBy', 'creator')
            ->addSelect('creator')
            ->orderBy('b.id', 'DESC')
            ->getQuery()
            ->getResult();

        $bookingIds = array_values(array_filter(array_map(
            static fn (Booking $b): ?int => $b->getId(),
            $bookings,
        )));
        $paymentsByBooking = $paymentRepository->findMapByBookingIds($bookingIds);

        $html = $this->renderView('booking/_rows.html.twig', [
            'bookings' => $bookings,
            'paymentsByBooking' => $paymentsByBooking,
        ]);

        $latestId = $bookingIds[0] ?? null;

        $response = new JsonResponse([
            'ok' => true,
            'latestId' => $latestId,
            'total' => \count($bookings),
            'fingerprint' => implode(',', $bookingIds),
            'rowsHtml' => $html,
        ]);
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate');

        return $response;
    }

    #[Route('/check-conflict', name: 'app_booking_check_conflict', methods: ['POST'])]
    public function checkConflict(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            if (!\is_array($data)) {
                return new JsonResponse(['hasConflict' => false, 'message' => '']);
            }

            $carId = $data['carId'] ?? null;
            $pickupDate = $data['pickupDate'] ?? null;
            $returnDate = $data['returnDate'] ?? null;
            $pickupTime = $data['pickupTime'] ?? null;
            $returnTime = $data['returnTime'] ?? null;
            $excludeBookingId = isset($data['excludeBookingId']) ? (int) $data['excludeBookingId'] : null;

            if (!$carId || !$pickupDate || !$returnDate || !$pickupTime || !$returnTime) {
                return new JsonResponse(['hasConflict' => false, 'message' => '']);
            }

            $pickupDateObj = new \DateTimeImmutable((string) $pickupDate);
            $returnDateObj = new \DateTimeImmutable((string) $returnDate);
            $pickupTimeObj = $this->scheduleValidator->parseTimeOnly((string) $pickupTime);
            $returnTimeObj = $this->scheduleValidator->parseTimeOnly((string) $returnTime);

            if (!$pickupTimeObj || !$returnTimeObj) {
                return new JsonResponse(['hasConflict' => false, 'message' => '']);
            }

            $hasConflict = $this->conflictChecker->hasConflict(
                (int) $carId,
                $pickupDateObj,
                $returnDateObj,
                $pickupTimeObj,
                $returnTimeObj,
                $excludeBookingId ?: null,
            );

            return new JsonResponse([
                'hasConflict' => $hasConflict,
                'message' => $hasConflict ? BookingConflictChecker::MESSAGE : '',
            ]);
        } catch (\Throwable $e) {
            error_log('checkConflict: '.$e->getMessage());

            return new JsonResponse([
                'hasConflict' => true,
                'message' => BookingConflictChecker::MESSAGE,
            ]);
        }
    }

    #[Route('/new', name: 'app_booking_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, BookingRepository $bookingRepository, LoginRepository $loginRepository): Response
    {
        $booking = new Booking();
        $form = $this->createForm(BookingType::class, $booking);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($this->bookingHasScheduleConflict($booking)) {
                $this->addFlash('error', BookingConflictChecker::MESSAGE);

                return $this->render('booking/new.html.twig', [
                    'booking' => $booking,
                    'form' => $form,
                ]);
            }

            $customerAccount = $loginRepository->findCustomerByPhone($booking->getPhone());
            if ($customerAccount !== null) {
                $booking->setCreatedBy($customerAccount);
            } else {
                $currentUser = $this->getUser();
                if ($currentUser instanceof Login) {
                    $loginEntity = $entityManager->getRepository(Login::class)->find($currentUser->getId());
                    $booking->setCreatedBy($loginEntity ?? $currentUser);
                }
            }

            $entityManager->persist($booking);
            $entityManager->flush();

            try {
                $this->bookingPaymentService->ensurePaymentForBooking($booking, $entityManager);
            } catch (\Exception $e) {
                error_log('Failed to create payment for booking: '.$e->getMessage());
            }

            $this->bookingNotifications->notifyBookingSubmitted($booking, $entityManager);
            $entityManager->flush();

            // Manually log the creation
            try {
                $user = $this->getUser();
                $username = $user ? $user->getUserIdentifier() : 'System';
                $roles = $user ? $user->getRoles() : [];
                $role = !empty($roles) ? implode(', ', $roles) : 'ANONYMOUS';
                
                $log = new ActivityLog();
                $log->setUser($username);
                $log->setRole($role);
                $log->setAction('CREATE');
                $log->setDateTime(new \DateTime('now'));
                $log->setEntityType('Booking');
                $log->setEntityId($booking->getId());
                
                $entityManager->persist($log);
                $entityManager->flush();
            } catch (\Exception $e) {
                // Silently fail if logging fails
                error_log('Failed to log booking creation: ' . $e->getMessage());
            }

            return $this->redirectToRoute('app_booking_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('booking/new.html.twig', [
            'booking' => $booking,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_booking_show', methods: ['GET'])]
    public function show(Booking $booking, PaymentRepository $paymentRepository): Response
    {
        $payment = $booking->getId() !== null
            ? $paymentRepository->findOneByBookingId($booking->getId())
            : null;

        return $this->render('booking/show.html.twig', [
            'booking' => $booking,
            'payment' => $payment,
        ]);
    }

    #[Route('/{id}/status', name: 'app_booking_update_status', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function updateStatus(
        Request $request,
        Booking $booking,
        EntityManagerInterface $entityManager,
        PaymentRepository $paymentRepository,
    ): Response {
        if (!$this->isCsrfTokenValid('booking_status_'.$booking->getId(), $request->request->getString('_token'))) {
            $this->addFlash('error', 'Invalid security token.');

            return $this->redirectToRoute('app_booking_index', [], Response::HTTP_SEE_OTHER);
        }

        $action = $request->request->getString('action');
        $previousStatus = $booking->getStatus();
        $payment = $booking->getId() !== null
            ? $paymentRepository->findOneByBookingId($booking->getId())
            : null;
        $previousPaymentStatus = $payment?->getStatus();

        $newStatus = match ($action) {
            'confirm' => BookingStatus::CONFIRMED,
            'decline', 'cancel' => BookingStatus::CANCELLED,
            'refund' => BookingStatus::REFUNDED,
            'complete' => BookingStatus::COMPLETED,
            default => null,
        };

        if ($newStatus === null) {
            $this->addFlash('error', 'Unknown action.');

            return $this->redirectAfterStatusChange($request, $booking);
        }

        $validationError = $this->validateAdminStatusAction($booking, $action, $payment);
        if ($validationError !== null) {
            $this->addFlash('error', $validationError);

            return $this->redirectAfterStatusChange($request, $booking);
        }

        if ($previousStatus === $newStatus) {
            $this->addFlash('info', 'Status is already '.$newStatus.'.');

            return $this->redirectAfterStatusChange($request, $booking);
        }

        $this->bookingPaymentService->applyAdminStatusChange($booking, $newStatus, $payment);
        $entityManager->flush();

        $this->bookingNotifications->notifyStatusChange($booking, $previousStatus, $entityManager);
        if ($booking->getStatus() !== BookingStatus::REFUNDED) {
            $this->bookingNotifications->notifyPaymentStatusChange(
                $booking,
                $previousPaymentStatus,
                $payment?->getStatus(),
                $entityManager,
            );
        }
        $entityManager->flush();

        $this->addFlash('success', match ($action) {
            'confirm' => 'Booking #'.$booking->getId().' confirmed.',
            'decline' => 'Booking #'.$booking->getId().' declined.',
            'cancel' => 'Booking #'.$booking->getId().' cancelled.',
            'refund' => 'Booking #'.$booking->getId().' marked as refunded.',
            'complete' => 'Booking #'.$booking->getId().' marked as completed.',
            default => 'Booking status updated.',
        });

        return $this->redirectAfterStatusChange($request, $booking);
    }

    #[Route('/{id}/edit', name: 'app_booking_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Booking $booking, EntityManagerInterface $entityManager, BookingRepository $bookingRepository, LoginRepository $loginRepository): Response
    {
        // Staff can edit any record, admins can edit all
        // No ownership check needed for edit

        $previousStatus = $booking->getStatus();

        $form = $this->createForm(BookingType::class, $booking);
        $form->handleRequest($request);

        if ($form->isSubmitted() && !$form->isValid()) {
            foreach ($form->getErrors(true) as $error) {
                $this->addFlash('error', $error->getMessage());
            }
        }

        if ($form->isSubmitted() && $form->isValid()) {
            // Server-side validation: Check if pickup date is in the past
            $now = new \DateTime();
            $pickupDateTime = null;
            $returnDateTime = null;
            
            if ($booking->getPickupDate() && $booking->getPickupTime()) {
                $pickupDateTime = clone $booking->getPickupDate();
                $pickupTime = $booking->getPickupTime();
                $pickupDateTime->setTime((int)$pickupTime->format('H'), (int)$pickupTime->format('i'), 0);
            }
            
            if ($booking->getReturnDate() && $booking->getReturnTime()) {
                $returnDateTime = clone $booking->getReturnDate();
                $returnTime = $booking->getReturnTime();
                $returnDateTime->setTime((int)$returnTime->format('H'), (int)$returnTime->format('i'), 0);
            }
            
            // Check if pickup date/time is in the past
            if ($pickupDateTime && $pickupDateTime < $now) {
                $this->addFlash('error', 'Pickup date/time cannot be in the past.');
                return $this->render('booking/edit.html.twig', [
                    'booking' => $booking,
                    'form' => $form,
                ]);
            }
            
            // Check if return date/time is in the past
            if ($returnDateTime && $returnDateTime < $now) {
                $this->addFlash('error', 'Return date/time cannot be in the past.');
                return $this->render('booking/edit.html.twig', [
                    'booking' => $booking,
                    'form' => $form,
                ]);
            }
            
            // Check if pickup is after return
            if ($pickupDateTime && $returnDateTime && $pickupDateTime >= $returnDateTime) {
                $this->addFlash('error', 'Pickup date/time cannot be after or equal to return date/time.');
                return $this->render('booking/edit.html.twig', [
                    'booking' => $booking,
                    'form' => $form,
                ]);
            }
            
            if ($this->bookingHasScheduleConflict($booking)) {
                $this->addFlash('error', BookingConflictChecker::MESSAGE);

                return $this->render('booking/edit.html.twig', [
                    'booking' => $booking,
                    'form' => $form,
                ]);
            }

            $customerAccount = $loginRepository->findCustomerByPhone($booking->getPhone());
            if ($customerAccount !== null) {
                $booking->setCreatedBy($customerAccount);
            }

            $entityManager->flush();

            $this->bookingNotifications->notifyStatusChange($booking, $previousStatus, $entityManager);
            $entityManager->flush();

            // Manually log the update
            try {
                $user = $this->getUser();
                $username = $user ? $user->getUserIdentifier() : 'System';
                $roles = $user ? $user->getRoles() : [];
                $role = !empty($roles) ? implode(', ', $roles) : 'ANONYMOUS';

                $log = new ActivityLog();
                $log->setUser($username);
                $log->setRole($role);
                $log->setAction('UPDATE');
                $log->setDateTime(new \DateTime('now'));
                $log->setEntityType('Booking');
                $log->setEntityId($booking->getId());
                
                $entityManager->persist($log);
                $entityManager->flush();
            } catch (\Exception $e) {
                // Silently fail if logging fails
                error_log('Failed to log booking update: ' . $e->getMessage());
            }

            return $this->redirectToRoute('app_booking_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('booking/edit.html.twig', [
            'booking' => $booking,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_booking_delete', methods: ['POST'])]
    public function delete(
        Request $request,
        Booking $booking,
        EntityManagerInterface $entityManager,
        BookingRepository $bookingRepository,
        PaymentRepository $paymentRepository,
    ): Response
    {
        // Staff can only delete their own records, admins can delete all
        $currentUser = $this->getUser();
        if ($currentUser && !in_array('ROLE_ADMIN', $currentUser->getRoles())) {
            // Load the booking with createdBy relationship to ensure it's available
            $bookingWithCreator = $bookingRepository->createQueryBuilder('b')
                ->leftJoin('b.createdBy', 'creator')
                ->addSelect('creator')
                ->where('b.id = :id')
                ->setParameter('id', $booking->getId())
                ->getQuery()
                ->getOneOrNullResult();
            
            if (!$bookingWithCreator) {
                $this->addFlash('error', 'Booking not found.');
                return $this->redirectToRoute('app_booking_index', [], Response::HTTP_SEE_OTHER);
            }
            
            $createdBy = $bookingWithCreator->getCreatedBy();
            if (!$createdBy || $createdBy->getId() !== $currentUser->getId()) {
                $this->addFlash('error', 'Access Denied');
                return $this->redirectToRoute('app_booking_index', [], Response::HTTP_SEE_OTHER);
            }
            
            // Use the loaded booking for deletion
            $booking = $bookingWithCreator;
        }
        
        if ($this->isCsrfTokenValid('delete'.$booking->getId(), $request->getPayload()->getString('_token'))) {
            $bookingId = $booking->getId();
            
            // Get car info before deletion, handling case where car might not exist
            $carInfo = 'N/A';
            try {
                $car = $booking->getCar();
                if ($car) {
                    $carInfo = $car->getBrand() . ' ' . $car->getModel();
                }
            } catch (\Exception $e) {
                // Car was deleted, use N/A
                $carInfo = 'N/A (Car Deleted)';
            }

            foreach ($paymentRepository->findBy(['booking' => $booking]) as $payment) {
                $entityManager->remove($payment);
            }

            $entityManager->remove($booking);
            $entityManager->flush();
            
            // Manually log the deletion (subscriber doesn't handle DELETE)
            try {
                $user = $this->getUser();
                $username = $user ? $user->getUserIdentifier() : 'System';
                $roles = $user ? $user->getRoles() : [];
                $role = !empty($roles) ? implode(', ', $roles) : 'ANONYMOUS';
                
                $log = new ActivityLog();
                $log->setUser($username);
                $log->setRole($role);
                $log->setAction('DELETE');
                $log->setDateTime(new \DateTime('now'));
                $log->setEntityType('Booking');
                $log->setEntityId($bookingId);
                
                $entityManager->persist($log);
                $entityManager->flush();
            } catch (\Exception $e) {
                // Silently fail if logging fails
                error_log('Failed to log booking deletion: ' . $e->getMessage());
            }
            
            $this->addFlash('success', 'Booking deleted successfully!');
        }

        return $this->redirectToRoute('app_booking_index', [], Response::HTTP_SEE_OTHER);
    }

    private function bookingHasScheduleConflict(Booking $booking): bool
    {
        $car = $booking->getCar();
        if ($car === null || $booking->getPickupDate() === null || $booking->getReturnDate() === null) {
            return false;
        }

        return $this->conflictChecker->hasConflict(
            $car->getId(),
            $booking->getPickupDate(),
            $booking->getReturnDate(),
            $booking->getPickupTime(),
            $booking->getReturnTime(),
            $booking->getId(),
        );
    }

    private function validateAdminStatusAction(Booking $booking, string $action, ?Payment $payment): ?string
    {
        $status = $booking->getStatus();

        return match ($action) {
            'confirm' => $status !== BookingStatus::PENDING
                ? 'Only pending bookings can be confirmed.'
                : null,
            'decline' => $status !== BookingStatus::PENDING
                ? 'Only pending bookings can be declined.'
                : null,
            'cancel' => $status !== BookingStatus::CONFIRMED
                ? 'Only confirmed bookings can be cancelled.'
                : null,
            'refund' => $status === BookingStatus::REFUNDED
                || !$payment instanceof Payment
                || !PaymentStatus::isPaid($payment->getStatus())
                ? 'Refund is only available for paid bookings that are not already refunded.'
                : null,
            'complete' => $status !== BookingStatus::CONFIRMED
                ? 'Only confirmed bookings can be marked completed.'
                : null,
            default => 'Invalid action.',
        };
    }

    private function redirectAfterStatusChange(Request $request, Booking $booking): Response
    {
        $referer = $request->headers->get('referer');
        if (\is_string($referer) && $referer !== '' && str_starts_with($referer, $request->getSchemeAndHttpHost())) {
            return $this->redirect($referer);
        }

        return $this->redirectToRoute('app_booking_show', ['id' => $booking->getId()], Response::HTTP_SEE_OTHER);
    }
}
