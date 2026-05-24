<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\DeviceToken;
use App\Entity\Login;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DeviceToken>
 */
class DeviceTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DeviceToken::class);
    }

  /**
   * @return list<DeviceToken>
   */
    public function findByLoginId(int $loginId): array
    {
        return $this->findBy(['login' => $loginId], ['updatedAt' => 'DESC']);
    }

    public function findOneByFcmToken(string $fcmToken): ?DeviceToken
    {
        return $this->findOneBy(['fcmToken' => $fcmToken]);
    }

    public function deleteForLoginAndToken(Login $login, string $fcmToken): bool
    {
        $token = $this->createQueryBuilder('d')
            ->andWhere('d.login = :login')
            ->andWhere('d.fcmToken = :fcmToken')
            ->setParameter('login', $login)
            ->setParameter('fcmToken', $fcmToken)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$token instanceof DeviceToken) {
            return false;
        }

        $this->getEntityManager()->remove($token);

        return true;
    }
}
