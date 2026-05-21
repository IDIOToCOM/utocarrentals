<?php

namespace App\Service;

use App\Entity\CarFavorite;
use App\Entity\Login;
use App\Repository\CarFavoriteRepository;
use App\Repository\CarInventoryRepository;
use Doctrine\ORM\EntityManagerInterface;

final class CarFavoriteService
{
    public function __construct(
        private readonly CarFavoriteRepository $favoriteRepository,
        private readonly CarInventoryRepository $carInventoryRepository,
    ) {
    }

    /**
     * @return list<int>
     */
    public function getFavoriteCarIds(Login $user): array
    {
        return $this->favoriteRepository->findCarIdsForUser($user->getId());
    }

    public function isFavorite(Login $user, int $carId): bool
    {
        return $this->favoriteRepository->findOneForUserAndCar($user->getId(), $carId) !== null;
    }

    /**
     * @return 'added'|'removed'
     */
    public function toggle(Login $user, int $carId, EntityManagerInterface $em): string
    {
        $car = $this->carInventoryRepository->findAvailableForCustomer($carId);
        if ($car === null) {
            throw new \InvalidArgumentException('This vehicle is not available to save.');
        }

        $existing = $this->favoriteRepository->findOneForUserAndCar($user->getId(), $carId);
        if ($existing instanceof CarFavorite) {
            $em->remove($existing);
            $em->flush();

            return 'removed';
        }

        $favorite = new CarFavorite();
        $favorite->setUser($user);
        $favorite->setCar($car);
        $em->persist($favorite);
        $em->flush();

        return 'added';
    }
}
