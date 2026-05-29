<?php

namespace App\Controller;

use App\Repository\AppNotificationRepository;
use App\Repository\CarInventoryRepository;
use App\Repository\BookingRepository;
use App\Entity\Login;
use App\Repository\LoginRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AdminController extends AbstractController
{
    #[Route('/admin', name: 'app_admin')]
    public function index(
        CarInventoryRepository $carRepository,
        BookingRepository $bookingRepository,
        LoginRepository $loginRepository
    ): Response
    {
        // Get statistics for the dashboard
        $totalCars = $carRepository->count([]);
        $activeBookings = $bookingRepository->count([]);
        $totalUsers = $loginRepository->count([]);
        
        return $this->render('admin/index.html.twig', [
            'controller_name' => 'AdminController',
            'totalCars' => $totalCars,
            'activeBookings' => $activeBookings,
            'totalUsers' => $totalUsers,
        ]);
    }

    #[Route('/admin/poll', name: 'app_admin_poll', methods: ['GET'])]
    public function poll(
        CarInventoryRepository $carRepository,
        BookingRepository $bookingRepository,
        LoginRepository $loginRepository,
        AppNotificationRepository $notificationRepository,
    ): JsonResponse {
        $user = $this->getUser();
        $unreadCount = 0;
        if ($user instanceof Login && $user->getId() !== null) {
            $unreadCount = $notificationRepository->countUnreadForUser((int) $user->getId());
        }

        return new JsonResponse([
            'ok' => true,
            'totalCars' => $carRepository->count([]),
            'activeBookings' => $bookingRepository->count([]),
            'totalUsers' => $loginRepository->count([]),
            'unreadNotifications' => $unreadCount,
        ]);
    }
}
