<?php

namespace App\Controller;

use App\Repository\BookingRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/booking')]
final class ApiBookingController extends AbstractController
{
    #[Route('', name: 'api_booking_index', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function index(BookingRepository $bookingRepository): JsonResponse
    {
        $bookings = $bookingRepository->createQueryBuilder('b')
            ->leftJoin('b.car', 'car')
            ->addSelect('car')
            ->leftJoin('b.createdBy', 'creator')
            ->addSelect('creator')
            ->orderBy('b.id', 'DESC')
            ->getQuery()
            ->getResult();

        $data = array_map(static function ($booking): array {
            return [
                'id' => $booking->getId(),
                'name' => $booking->getName(),
                'pickupLocation' => $booking->getPickupLocation(),
                'dropoffLocation' => $booking->getDropoffLocation(),
                'pickupDate' => $booking->getPickupDate()?->format('Y-m-d'),
                'returnDate' => $booking->getReturnDate()?->format('Y-m-d'),
                'pickupTime' => $booking->getPickupTime()?->format('H:i:s'),
                'returnTime' => $booking->getReturnTime()?->format('H:i:s'),
                'car' => $booking->getCar() ? [
                    'id' => $booking->getCar()->getId(),
                    'brand' => $booking->getCar()->getBrand(),
                    'model' => $booking->getCar()->getModel(),
                ] : null,
                'createdBy' => $booking->getCreatedBy()?->getUserIdentifier(),
            ];
        }, $bookings);

        return $this->json($data);
    }
}
