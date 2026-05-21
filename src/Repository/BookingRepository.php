<?php

namespace App\Repository;

use App\Booking\BookingStatus;
use App\Entity\Booking;
use App\Entity\Login;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Booking>
 */
class BookingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Booking::class);
    }

    public function countByCarId(int $carId): int
    {
        return (int) $this->createQueryBuilder('b')
            ->select('COUNT(b.id)')
            ->where('b.car = :carId')
            ->setParameter('carId', $carId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Non-cancelled / non-refunded bookings per car (used for “popular” fleet ranking).
     *
     * @param int[] $carIds
     *
     * @return array<int, int> car id => booking count
     */
    public function countActiveBookingsByCarIds(array $carIds): array
    {
        if ($carIds === []) {
            return [];
        }

        $rows = $this->createQueryBuilder('b')
            ->select('IDENTITY(b.car) AS carId', 'COUNT(b.id) AS bookingCount')
            ->where('b.car IN (:carIds)')
            ->andWhere('b.status NOT IN (:inactive)')
            ->setParameter('carIds', $carIds)
            ->setParameter('inactive', [BookingStatus::CANCELLED, BookingStatus::REFUNDED])
            ->groupBy('b.car')
            ->getQuery()
            ->getScalarResult();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(int) $row['carId']] = (int) $row['bookingCount'];
        }

        return $counts;
    }

    /**
     * @return list<Booking>
     */
    public function findByCarId(int $carId): array
    {
        return $this->createQueryBuilder('b')
            ->where('b.car = :carId')
            ->setParameter('carId', $carId)
            ->orderBy('b.pickupDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<Booking>
     */
    public function findByCreatedById(int $loginId): array
    {
        return $this->createQueryBuilder('b')
            ->leftJoin('b.car', 'car')->addSelect('car')
            ->where('IDENTITY(b.createdBy) = :loginId')
            ->setParameter('loginId', $loginId)
            ->orderBy('b.pickupDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Bookings owned by this customer: linked account and/or same phone on the booking.
     *
     * @return list<Booking>
     */
    public function findForCustomer(Login $login, bool $linkUnassigned = false, ?EntityManagerInterface $em = null): array
    {
        $loginId = (int) $login->getId();
        $byId = [];

        foreach ($this->findByCreatedById($loginId) as $booking) {
            if ($booking->getId() !== null) {
                $byId[$booking->getId()] = $booking;
            }
        }

        $phoneDigits = self::normalizePhoneDigits($login->getPhone());
        if (strlen($phoneDigits) >= 10) {
            $candidates = $this->createQueryBuilder('b')
                ->leftJoin('b.car', 'car')->addSelect('car')
                ->where('b.phone IS NOT NULL')
                ->andWhere('IDENTITY(b.createdBy) IS NULL OR IDENTITY(b.createdBy) != :loginId')
                ->setParameter('loginId', $loginId)
                ->getQuery()
                ->getResult();

            foreach ($candidates as $booking) {
                if ($booking->getId() === null || isset($byId[$booking->getId()])) {
                    continue;
                }
                if (self::normalizePhoneDigits($booking->getPhone()) !== $phoneDigits) {
                    continue;
                }

                if ($linkUnassigned && $em !== null) {
                    $booking->setCreatedBy($login);
                    $em->persist($booking);
                }

                $byId[$booking->getId()] = $booking;
            }
        }

        if ($linkUnassigned && $em !== null) {
            $em->flush();
        }

        return $this->sortByPickupDateDesc(array_values($byId));
    }

    public function bookingBelongsToCustomer(Booking $booking, Login $login): bool
    {
        if ($booking->getCreatedBy()?->getId() === $login->getId()) {
            return true;
        }

        $phoneDigits = self::normalizePhoneDigits($login->getPhone());
        if (strlen($phoneDigits) < 10) {
            return false;
        }

        return self::normalizePhoneDigits($booking->getPhone()) === $phoneDigits;
    }

    public function findOneForCustomer(int $bookingId, Login $login): ?Booking
    {
        $booking = $this->createQueryBuilder('b')
            ->leftJoin('b.car', 'car')->addSelect('car')
            ->where('b.id = :id')
            ->setParameter('id', $bookingId)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$booking instanceof Booking) {
            return null;
        }

        return $this->bookingBelongsToCustomer($booking, $login) ? $booking : null;
    }

    /** @deprecated use findOneForCustomer() */
    public function findOneForOwner(int $bookingId, int $loginId): ?Booking
    {
        $login = $this->getEntityManager()->getRepository(Login::class)->find($loginId);

        return $login instanceof Login ? $this->findOneForCustomer($bookingId, $login) : null;
    }

    public static function normalizePhoneDigits(?string $phone): string
    {
        return preg_replace('/\D+/', '', (string) $phone) ?? '';
    }

    /**
     * @param list<Booking> $bookings
     *
     * @return list<Booking>
     */
    private function sortByPickupDateDesc(array $bookings): array
    {
        usort($bookings, static function (Booking $a, Booking $b): int {
            $ta = $a->getPickupDate()?->getTimestamp() ?? 0;
            $tb = $b->getPickupDate()?->getTimestamp() ?? 0;

            return $tb <=> $ta;
        });

        return $bookings;
    }

    public function countByCarIds(array $carIds): array
    {
        if ($carIds === []) {
            return [];
        }

        $rows = $this->createQueryBuilder('b')
            ->select('IDENTITY(b.car) AS carId')
            ->addSelect('COUNT(b.id) AS cnt')
            ->where('b.car IN (:ids)')
            ->setParameter('ids', $carIds)
            ->groupBy('b.car')
            ->getQuery()
            ->getScalarResult();

        $map = [];
        foreach ($rows as $row) {
            if ($row['carId'] === null) {
                continue;
            }
            $map[(int) $row['carId']] = (int) $row['cnt'];
        }

        return $map;
    }

    //    /**
    //     * @return Booking[] Returns an array of Booking objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('b')
    //            ->andWhere('b.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('b.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    /**
     * Find conflicting bookings for a car within a date/time range
     * Excludes the current booking (for edit operations)
     */
    public function findConflictingBookings(
        $carId,
        \DateTime $pickupDate,
        \DateTime $returnDate,
        \DateTime $pickupTime,
        \DateTime $returnTime,
        ?int $excludeBookingId = null
    ): array {
        // Normalize dates to midnight for comparison
        $pickupDateNormalized = clone $pickupDate;
        $pickupDateNormalized->setTime(0, 0, 0);
        $returnDateNormalized = clone $returnDate;
        $returnDateNormalized->setTime(0, 0, 0);
        
        $qb = $this->createQueryBuilder('b')
            ->where('b.car = :carId')
            ->setParameter('carId', $carId);

        // Exclude current booking if editing
        if ($excludeBookingId !== null) {
            $qb->andWhere('b.id != :excludeId')
               ->setParameter('excludeId', $excludeBookingId);
        }

        // Find bookings where date ranges overlap
        // Overlap occurs when:
        // - New pickup is between existing pickup and return
        // - New return is between existing pickup and return
        // - New booking completely encompasses existing booking
        // - Existing booking completely encompasses new booking
        $qb->andWhere(
            $qb->expr()->orX(
                // New pickup date is within existing booking range
                $qb->expr()->andX(
                    $qb->expr()->lte('b.pickupDate', ':newPickupDate'),
                    $qb->expr()->gte('b.returnDate', ':newPickupDate')
                ),
                // New return date is within existing booking range
                $qb->expr()->andX(
                    $qb->expr()->lte('b.pickupDate', ':newReturnDate'),
                    $qb->expr()->gte('b.returnDate', ':newReturnDate')
                ),
                // New booking completely encompasses existing booking
                $qb->expr()->andX(
                    $qb->expr()->lte(':newPickupDate', 'b.pickupDate'),
                    $qb->expr()->gte(':newReturnDate', 'b.returnDate')
                ),
                // Existing booking completely encompasses new booking
                $qb->expr()->andX(
                    $qb->expr()->lte('b.pickupDate', ':newPickupDate'),
                    $qb->expr()->gte('b.returnDate', ':newReturnDate')
                )
            )
        )
        ->setParameter('newPickupDate', $pickupDateNormalized, \Doctrine\DBAL\Types\Types::DATETIME_MUTABLE)
        ->setParameter('newReturnDate', $returnDateNormalized, \Doctrine\DBAL\Types\Types::DATETIME_MUTABLE);

        return $qb->getQuery()->getResult();
    }
}
