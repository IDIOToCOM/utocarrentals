<?php

namespace App\Repository;

use App\Car\CarCatalogSort;
use App\Car\CarFleetStatus;
use App\Entity\CarInventory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CarInventory>
 */
class CarInventoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CarInventory::class);
    }

    /**
     * @return list<CarInventory>
     */
    public function findForCustomerCatalog(
        ?string $type = null,
        ?string $search = null,
        string $sort = CarCatalogSort::DEFAULT,
    ): array {
        $qb = $this->createQueryBuilder('c')
            ->andWhere('c.Status = :status')
            ->setParameter('status', CarFleetStatus::AVAILABLE);

        if ($type !== null && $type !== '') {
            $qb->andWhere('c.Type = :type')
                ->setParameter('type', $type);
        }

        $search = $search !== null ? trim($search) : '';
        if ($search !== '') {
            $term = '%'.addcslashes(mb_strtolower($search), '%_\\').'%';
            $qb->andWhere('LOWER(c.Brand) LIKE :search OR LOWER(c.Model) LIKE :search')
                ->setParameter('search', $term);
        }

        match (CarCatalogSort::normalize($sort)) {
            CarCatalogSort::PRICE_DESC => $qb->orderBy('c.Price_per_day', 'DESC')->addOrderBy('c.Brand', 'ASC'),
            CarCatalogSort::NAME_ASC => $qb->orderBy('c.Brand', 'ASC')->addOrderBy('c.Model', 'ASC'),
            default => $qb->orderBy('c.Price_per_day', 'ASC')->addOrderBy('c.Brand', 'ASC'),
        };

        return $qb->getQuery()->getResult();
    }

    public function findAvailableForCustomer(int $id): ?CarInventory
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.id = :id')
            ->andWhere('c.Status = :status')
            ->setParameter('id', $id)
            ->setParameter('status', CarFleetStatus::AVAILABLE)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @param list<int> $ids
     *
     * @return list<CarInventory> Preserves order of $ids where car is still available
     */
    public function findAvailableForCustomerByIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $cars = $this->createQueryBuilder('c')
            ->andWhere('c.id IN (:ids)')
            ->andWhere('c.Status = :status')
            ->setParameter('ids', $ids)
            ->setParameter('status', CarFleetStatus::AVAILABLE)
            ->getQuery()
            ->getResult();

        $byId = [];
        foreach ($cars as $car) {
            if ($car instanceof CarInventory && $car->getId() !== null) {
                $byId[$car->getId()] = $car;
            }
        }

        $ordered = [];
        foreach ($ids as $id) {
            if (isset($byId[$id])) {
                $ordered[] = $byId[$id];
            }
        }

        return $ordered;
    }

    /**
     * @return list<string>
     */
    public function findDistinctTypes(): array
    {
        $rows = $this->createQueryBuilder('c')
            ->select('DISTINCT c.Type AS type')
            ->andWhere('c.Status = :status')
            ->setParameter('status', CarFleetStatus::AVAILABLE)
            ->orderBy('c.Type', 'ASC')
            ->getQuery()
            ->getScalarResult();

        return array_values(array_filter(array_map(
            static fn (array $row): string => (string) ($row['type'] ?? ''),
            $rows
        )));
    }
}
