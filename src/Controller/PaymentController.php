<?php

namespace App\Controller;

use App\Entity\Payment;
use App\Entity\Login;
use App\Entity\ActivityLog;
use App\Form\PaymentType;
use App\Repository\PaymentRepository;
use App\Service\BookingNotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/payment')]
final class PaymentController extends AbstractController
{
    #[Route(name: 'app_payment_index', methods: ['GET'])]
    public function index(PaymentRepository $paymentRepository): Response
    {
        // Staff can see all payments, but can only edit/delete their own
        // Admins can see and do everything
        $payments = $paymentRepository->createQueryBuilder('p')
            ->leftJoin('p.car', 'car')
            ->addSelect('car')
            ->leftJoin('p.booking', 'booking')
            ->addSelect('booking')
            ->leftJoin('p.createdBy', 'creator')
            ->addSelect('creator')
            ->getQuery()
            ->getResult();

        return $this->render('payment/index.html.twig', [
            'payments' => $payments,
        ]);
    }

    #[Route('/poll', name: 'app_payment_poll', methods: ['GET'])]
    public function poll(PaymentRepository $paymentRepository): JsonResponse
    {
        $payments = $paymentRepository->createQueryBuilder('p')
            ->leftJoin('p.car', 'car')
            ->addSelect('car')
            ->leftJoin('p.booking', 'booking')
            ->addSelect('booking')
            ->leftJoin('p.createdBy', 'creator')
            ->addSelect('creator')
            ->orderBy('p.id', 'DESC')
            ->getQuery()
            ->getResult();

        $html = $this->renderView('payment/_rows.html.twig', [
            'payments' => $payments,
        ]);

        $latestId = null;
        $ids = [];
        foreach ($payments as $payment) {
            $id = $payment->getId();
            if ($id !== null) {
                $ids[] = $id;
            }
        }
        if ($ids !== []) {
            $latestId = $ids[0];
        }

        $response = new JsonResponse([
            'ok' => true,
            'latestId' => $latestId,
            'total' => \count($payments),
            'fingerprint' => implode(',', $ids),
            'rowsHtml' => $html,
        ]);
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate');

        return $response;
    }

    #[Route('/new', name: 'app_payment_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $payment = new Payment();
        $form = $this->createForm(PaymentType::class, $payment);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Set createdBy for ownership tracking
            $currentUser = $this->getUser();
            if ($currentUser instanceof Login) {
                // Refresh the entity to ensure it's managed
                $loginEntity = $entityManager->getRepository(Login::class)->find($currentUser->getId());
                if ($loginEntity) {
                    $payment->setCreatedBy($loginEntity);
                } else {
                    // If not found, try to use the current user directly
                    $payment->setCreatedBy($currentUser);
                }
            }
            
            $entityManager->persist($payment);
            $entityManager->flush();
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
                $log->setEntityType('Payment');
                $log->setEntityId($payment->getId());

                $entityManager->persist($log);
                $entityManager->flush();
            } catch (\Exception $e) {
                error_log('Failed to log payment creation: ' . $e->getMessage());
            }

            return $this->redirectToRoute('app_payment_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('payment/new.html.twig', [
            'payment' => $payment,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_payment_show', methods: ['GET'])]
    public function show(Payment $payment): Response
    {
        // Staff can only view their own records
        $currentUser = $this->getUser();
        if ($currentUser && !in_array('ROLE_ADMIN', $currentUser->getRoles())) {
            if (!$payment->getCreatedBy() || $payment->getCreatedBy()->getId() !== $currentUser->getId()) {
                $this->addFlash('error', 'Access Denied');
                return $this->redirectToRoute('app_payment_index', [], Response::HTTP_SEE_OTHER);
            }
        }
        
        return $this->render('payment/show.html.twig', [
            'payment' => $payment,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_payment_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        Payment $payment,
        EntityManagerInterface $entityManager,
        BookingNotificationService $bookingNotifications,
    ): Response
    {
        // Staff can edit any record, admins can edit all
        // No ownership check needed for edit

        $previousPaymentStatus = $payment->getStatus();
        $form = $this->createForm(PaymentType::class, $payment);
        $form->handleRequest($request);

        if ($form->isSubmitted() && !$form->isValid()) {
            $this->addFlash('error', 'Could not save the payment. Please fix the errors below.');
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $paymentId = $payment->getId();
            $entityManager->flush();

            $booking = $payment->getBooking();
            if ($booking !== null) {
                $bookingNotifications->notifyPaymentStatusChange(
                    $booking,
                    $previousPaymentStatus,
                    $payment->getStatus(),
                    $entityManager,
                );
                $entityManager->flush();
            }

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
                $log->setEntityType('Payment');
                $log->setEntityId($paymentId);

                $entityManager->persist($log);
                $entityManager->flush();
            } catch (\Exception $e) {
                error_log('Failed to log payment update: ' . $e->getMessage());
            }

            $this->addFlash('success', 'Payment updated successfully.');

            return $this->redirectToRoute('app_payment_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('payment/edit.html.twig', [
            'payment' => $payment,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_payment_delete', methods: ['POST'])]
    public function delete(Request $request, Payment $payment, EntityManagerInterface $entityManager, PaymentRepository $paymentRepository): Response
    {
        // Staff can only delete their own records, admins can delete all
        $currentUser = $this->getUser();
        if ($currentUser && !in_array('ROLE_ADMIN', $currentUser->getRoles())) {
            // Load the payment with createdBy relationship to ensure it's available
            $paymentWithCreator = $paymentRepository->createQueryBuilder('p')
                ->leftJoin('p.createdBy', 'creator')
                ->addSelect('creator')
                ->where('p.id = :id')
                ->setParameter('id', $payment->getId())
                ->getQuery()
                ->getOneOrNullResult();
            
            if (!$paymentWithCreator) {
                $this->addFlash('error', 'Payment not found.');
                return $this->redirectToRoute('app_payment_index', [], Response::HTTP_SEE_OTHER);
            }
            
            $createdBy = $paymentWithCreator->getCreatedBy();
            if (!$createdBy || $createdBy->getId() !== $currentUser->getId()) {
                $this->addFlash('error', 'Access Denied');
                return $this->redirectToRoute('app_payment_index', [], Response::HTTP_SEE_OTHER);
            }
            
            // Use the loaded payment for deletion
            $payment = $paymentWithCreator;
        }
        
        if ($this->isCsrfTokenValid('delete'.$payment->getId(), $request->getPayload()->getString('_token'))) {
            $paymentId = $payment->getId();
            
            $entityManager->remove($payment);
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
                $log->setEntityType('Payment');
                $log->setEntityId($paymentId);
                
                $entityManager->persist($log);
                $entityManager->flush();
            } catch (\Exception $e) {
                error_log('Failed to log payment deletion: ' . $e->getMessage());
            }
            
            $this->addFlash('success', 'Payment deleted successfully!');
        }

        return $this->redirectToRoute('app_payment_index', [], Response::HTTP_SEE_OTHER);
    }
}
