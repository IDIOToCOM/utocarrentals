<?php

namespace App\Repository;

use App\Entity\Login;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<Login>
 */
class LoginRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Login::class);
    }

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof Login) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    public function findOneByIdentifier(string $identifier): ?Login
    {
        $identifier = trim($identifier);
        if ($identifier === '') {
            return null;
        }

        return $this->createQueryBuilder('l')
            ->where('l.username = :id OR l.email = :id')
            ->setParameter('id', $identifier)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Customer account (ROLE_USER) with the same phone digits as on a booking.
     */
    public function findCustomerByPhone(?string $phone): ?Login
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';
        if (strlen($digits) < 10) {
            return null;
        }

        $accounts = $this->createQueryBuilder('l')
            ->where('l.phone IS NOT NULL')
            ->andWhere('l.roles LIKE :role')
            ->setParameter('role', '%ROLE_USER%')
            ->getQuery()
            ->getResult();

        foreach ($accounts as $account) {
            if (!$account instanceof Login) {
                continue;
            }
            $accountDigits = preg_replace('/\D+/', '', (string) $account->getPhone()) ?? '';
            if ($accountDigits !== '' && $accountDigits === $digits) {
                return $account;
            }
        }

        return null;
    }

    /**
     * Admin and staff accounts that receive operational alerts.
     *
     * @return list<Login>
     */
    public function findStaffRecipients(): array
    {
        $accounts = $this->createQueryBuilder('l')
            ->where('l.isEnabled = true')
            ->andWhere('l.roles LIKE :admin OR l.roles LIKE :staff')
            ->setParameter('admin', '%ROLE_ADMIN%')
            ->setParameter('staff', '%ROLE_STAFF%')
            ->getQuery()
            ->getResult();

        return array_values(array_filter(
            $accounts,
            static fn ($login): bool => $login instanceof Login
                && $login->getId() !== null
                && (
                    \in_array('ROLE_ADMIN', $login->getRoles(), true)
                    || \in_array('ROLE_STAFF', $login->getRoles(), true)
                ),
        ));
    }

    //    /**
    //     * @return Login[] Returns an array of Login objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('l')
    //            ->andWhere('l.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('l.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Login
    //    {
    //        return $this->createQueryBuilder('l')
    //            ->andWhere('l.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
