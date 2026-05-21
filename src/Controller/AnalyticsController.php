<?php

namespace App\Controller;

use App\Repository\CarInventoryRepository;
use App\Repository\BookingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class AnalyticsController extends AbstractController
{
    #[Route('/analytics', name: 'app_analytics')]
    #[IsGranted('ROLE_USER')]
    public function index(
        CarInventoryRepository $carInventoryRepository,
        BookingRepository $bookingRepository,
        EntityManagerInterface $entityManager
    ): Response {
        // 1. Car Types Distribution
        $carTypes = ['SUV', 'Sports Car', 'Sedan', 'Hatchback', 'Truck'];
        $typeCounts = [];
        foreach ($carTypes as $type) {
            $count = $carInventoryRepository->createQueryBuilder('c')
                ->select('COUNT(c.id)')
                ->where('c.Type = :type')
                ->setParameter('type', $type)
                ->getQuery()
                ->getSingleScalarResult();
            $typeCounts[$type] = (int)$count;
        }

        // 2. Most Rented Cars
        $mostRentedCars = $entityManager->createQueryBuilder()
            ->select('c.id, c.Brand, c.Model, COUNT(b.id) as rentalCount')
            ->from('App\Entity\CarInventory', 'c')
            ->leftJoin('c.bookings', 'b')
            ->groupBy('c.id')
            ->orderBy('rentalCount', 'DESC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();

        $rentedCarsData = [];
        foreach ($mostRentedCars as $car) {
            $carName = $car['Brand'] . ' ' . $car['Model'];
            $rentedCarsData[$carName] = (int)$car['rentalCount'];
        }

        // 3. Monthly Rental Distribution
        $allBookings = $bookingRepository->findAll();
        $monthNames = [
            1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
            5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
            9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
        ];

        $monthlyData = [];
        // Initialize all months with 0
        foreach ($monthNames as $num => $name) {
            $monthlyData[$name] = 0;
        }

        // Count rentals by month
        foreach ($allBookings as $booking) {
            if ($booking->getPickupDate()) {
                $monthNum = (int)$booking->getPickupDate()->format('n');
                $monthName = $monthNames[$monthNum] ?? 'Unknown';
                if (isset($monthlyData[$monthName])) {
                    $monthlyData[$monthName]++;
                }
            }
        }

        return $this->render('analytics/index.html.twig', [
            'carTypesData' => $typeCounts,
            'rentedCarsData' => $rentedCarsData,
            'monthlyData' => $monthlyData,
        ]);
    }
}

