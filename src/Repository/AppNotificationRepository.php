<?php

namespace App\Repository;

use App\Entity\AppNotification;
use App\Entity\Login;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AppNotification>
 */
class AppNotificationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AppNotification::class);
    }

    public function countUnreadForUser(int $loginId): int
    {
        return (int) $this->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->where('IDENTITY(n.recipient) = :uid')
            ->andWhere('n.readAt IS NULL')
            ->setParameter('uid', $loginId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return list<AppNotification>
     */
    public function findRecentForUser(int $loginId, int $limit = 8): array
    {
        return $this->createQueryBuilder('n')
            ->leftJoin('n.booking', 'b')->addSelect('b')
            ->where('IDENTITY(n.recipient) = :uid')
            ->setParameter('uid', $loginId)
            ->orderBy('n.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<AppNotification>
     */
    public function findAllForUser(int $loginId, int $limit = 50): array
    {
        return $this->createQueryBuilder('n')
            ->leftJoin('n.booking', 'b')->addSelect('b')
            ->where('IDENTITY(n.recipient) = :uid')
            ->setParameter('uid', $loginId)
            ->orderBy('n.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function existsForBookingRecipientAndType(int $bookingId, int $recipientId, string $type): bool
    {
        return (int) $this->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->where('IDENTITY(n.booking) = :bid')
            ->andWhere('IDENTITY(n.recipient) = :uid')
            ->andWhere('n.type = :type')
            ->setParameter('bid', $bookingId)
            ->setParameter('uid', $recipientId)
            ->setParameter('type', $type)
            ->getQuery()
            ->getSingleScalarResult() > 0;
    }

    public function markAllReadForUser(int $loginId): int
    {
        return $this->createQueryBuilder('n')
            ->update()
            ->set('n.readAt', ':now')
            ->where('IDENTITY(n.recipient) = :uid')
            ->andWhere('n.readAt IS NULL')
            ->setParameter('now', new \DateTimeImmutable())
            ->setParameter('uid', $loginId)
            ->getQuery()
            ->execute();
    }

    public function findOneForUser(int $notificationId, int $loginId): ?AppNotification
    {
        return $this->createQueryBuilder('n')
            ->where('n.id = :id')
            ->andWhere('IDENTITY(n.recipient) = :uid')
            ->setParameter('id', $notificationId)
            ->setParameter('uid', $loginId)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
