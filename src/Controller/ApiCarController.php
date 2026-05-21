<?php

namespace App\Controller;

use App\Repository\CarInventoryRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/car')]
final class ApiCarController extends AbstractController
{
    #[Route('', name: 'api_car_index', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function index(CarInventoryRepository $carInventoryRepository): JsonResponse
    {
        $cars = $carInventoryRepository->createQueryBuilder('c')
            ->leftJoin('c.createdBy', 'creator')
            ->addSelect('creator')
            ->orderBy('c.id', 'DESC')
            ->getQuery()
            ->getResult();

        $data = array_map(static function ($car): array {
            return [
                'id' => $car->getId(),
                'brand' => $car->getBrand(),
                'model' => $car->getModel(),
                'type' => $car->getType(),
                'pricePerDay' => $car->getPricePerDay(),
                'status' => $car->getStatus(),
                'createdBy' => $car->getCreatedBy()?->getUserIdentifier(),
            ];
        }, $cars);

        return $this->json($data);
    }
}
