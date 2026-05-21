<?php

namespace App\Repository;

use App\Car\CarReviewSummary;
use App\Entity\CarReview;
use App\Entity\Login;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CarReview>
 */
class CarReviewRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CarReview::class);
    }

    public function findOneByAuthorAndCar(int $loginId, int $carId): ?CarReview
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.author = :loginId')
            ->andWhere('r.car = :carId')
            ->setParameter('loginId', $loginId)
            ->setParameter('carId', $carId)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function getSummaryForCar(int $carId): CarReviewSummary
    {
        $row = $this->createQueryBuilder('r')
            ->select('COUNT(r.id) AS reviewCount', 'AVG(r.rating) AS averageRating')
            ->andWhere('r.car = :carId')
            ->setParameter('carId', $carId)
            ->getQuery()
            ->getOneOrNullResult();

        if (!\is_array($row)) {
            return new CarReviewSummary();
        }

        $count = (int) ($row['reviewCount'] ?? 0);
        $avg = $row['averageRating'] !== null ? round((float) $row['averageRating'], 1) : null;

        return new CarReviewSummary($count, $avg);
    }

    /**
     * @param list<int> $carIds
     *
     * @return array<int, CarReviewSummary> car id => summary
     */
    public function getSummariesForCarIds(array $carIds): array
    {
        if ($carIds === []) {
            return [];
        }

        $rows = $this->createQueryBuilder('r')
            ->select('IDENTITY(r.car) AS carId', 'COUNT(r.id) AS reviewCount', 'AVG(r.rating) AS averageRating')
            ->andWhere('r.car IN (:ids)')
            ->setParameter('ids', $carIds)
            ->groupBy('r.car')
            ->getQuery()
            ->getResult();

        $map = [];
        foreach ($carIds as $id) {
            $map[$id] = new CarReviewSummary();
        }

        foreach ($rows as $row) {
            $carId = (int) ($row['carId'] ?? 0);
            if ($carId <= 0) {
                continue;
            }
            $map[$carId] = new CarReviewSummary(
                (int) ($row['reviewCount'] ?? 0),
                $row['averageRating'] !== null ? round((float) $row['averageRating'], 1) : null,
            );
        }

        return $map;
    }

    /**
     * @return list<CarReview>
     */
    public function findForCarOrdered(int $carId, int $limit = 50): array
    {
        return $this->createQueryBuilder('r')
            ->addSelect('author')
            ->innerJoin('r.author', 'author')
            ->andWhere('r.car = :carId')
            ->setParameter('carId', $carId)
            ->orderBy('r.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<CarReview>
     */
    public function findForCarAdmin(int $carId): array
    {
        return $this->findForCarOrdered($carId, 100);
    }
}
