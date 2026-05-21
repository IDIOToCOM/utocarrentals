<?php

namespace App\Repository;

use App\Entity\Payment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Payment>
 */
class PaymentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Payment::class);
    }

    public function findOneByBookingId(int $bookingId): ?Payment
    {
        return $this->createQueryBuilder('p')
            ->andWhere('IDENTITY(p.booking) = :bookingId')
            ->setParameter('bookingId', $bookingId)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @param int[] $bookingIds
     *
     * @return array<int, Payment> booking id => payment
     */
    public function findMapByBookingIds(array $bookingIds): array
    {
        if ($bookingIds === []) {
            return [];
        }

        $payments = $this->createQueryBuilder('p')
            ->andWhere('IDENTITY(p.booking) IN (:ids)')
            ->setParameter('ids', $bookingIds)
            ->getQuery()
            ->getResult();

        $map = [];
        foreach ($payments as $payment) {
            $booking = $payment->getBooking();
            if ($booking?->getId() !== null) {
                $map[$booking->getId()] = $payment;
            }
        }

        return $map;
    }
}
