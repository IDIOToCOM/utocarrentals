<?php

namespace App\Controller;

use App\Repository\CarInventoryRepository;
use App\Repository\BookingRepository;
use App\Repository\LoginRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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
}
