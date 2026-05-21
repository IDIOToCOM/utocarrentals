<?php

namespace App\Repository;

use App\Entity\CarFavorite;
use App\Entity\Login;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CarFavorite>
 */
class CarFavoriteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CarFavorite::class);
    }

    /**
     * @return list<int>
     */
    public function findCarIdsForUser(int $userId): array
    {
        $rows = $this->createQueryBuilder('f')
            ->select('IDENTITY(f.car) AS carId')
            ->andWhere('f.user = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('f.createdAt', 'DESC')
            ->getQuery()
            ->getScalarResult();

        return array_map(static fn (array $row): int => (int) $row['carId'], $rows);
    }

    public function findOneForUserAndCar(int $userId, int $carId): ?CarFavorite
    {
        return $this->createQueryBuilder('f')
            ->andWhere('f.user = :userId')
            ->andWhere('f.car = :carId')
            ->setParameter('userId', $userId)
            ->setParameter('carId', $carId)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function countForUser(int $userId): int
    {
        return (int) $this->createQueryBuilder('f')
            ->select('COUNT(f.id)')
            ->andWhere('f.user = :userId')
            ->setParameter('userId', $userId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return list<CarFavorite>
     */
    public function findForUserOrdered(Login $user): array
    {
        return $this->createQueryBuilder('f')
            ->addSelect('car')
            ->innerJoin('f.car', 'car')
            ->andWhere('f.user = :user')
            ->setParameter('user', $user)
            ->orderBy('f.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
