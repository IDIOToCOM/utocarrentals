<?php

namespace App\EventSubscriber;

use App\Entity\ActivityLog;
use App\Entity\Booking;
use App\Entity\CarInventory;
use App\Entity\Login;
use App\Entity\Payment;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\Proxy;
use Symfony\Bundle\SecurityBundle\Security;
use Doctrine\ORM\Events;
use Doctrine\Common\EventSubscriber as DoctrineEventSubscriber;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;

class ActivityLogSubscriber implements DoctrineEventSubscriber
{
    private EntityManagerInterface $entityManager;
    private Security $security;

    public function __construct(EntityManagerInterface $entityManager, Security $security)
    {
        $this->entityManager = $entityManager;
        $this->security = $security;
    }

    public function getSubscribedEvents(): array
    {
        return [
            Events::postPersist,
            Events::postUpdate,
            Events::postFlush,
        ];
    }

    public function postPersist(PostPersistEventArgs $args): void
    {
        $entity = $args->getObject();
        
        // Skip logging ActivityLog itself to avoid recursion
        if ($entity instanceof ActivityLog) {
            return;
        }

        // Only log entities we care about
        $entityType = $this->getEntityType($entity);
        if (!$entityType) {
            return;
        }

        // Persist immediately to avoid relying on postFlush queueing.
        $em = $args->getObjectManager();

        try {
            $this->createLogEntry($em, 'CREATE', $entityType, $entity->getId());
            $em->flush();
        } catch (\Throwable $e) {
            error_log('Failed to create activity log (CREATE): ' . $e->getMessage());
        }
    }

    public function postUpdate(PostUpdateEventArgs $args): void
    {
        $entity = $args->getObject();
        
        if ($entity instanceof ActivityLog) {
            return;
        }

        $entityType = $this->getEntityType($entity);
        if (!$entityType) {
            return;
        }

        // Persist immediately to avoid relying on postFlush queueing.
        $em = $args->getObjectManager();

        try {
            $this->createLogEntry($em, 'UPDATE', $entityType, $entity->getId());
            $em->flush();
        } catch (\Throwable $e) {
            error_log('Failed to create activity log (UPDATE): ' . $e->getMessage());
        }
    }


    private bool $isFlushing = false;

    public function postFlush(PostFlushEventArgs $args): void
    {
        // Intentionally left empty:
        // we persist ActivityLog rows immediately in postPersist/postUpdate.
        // postFlush is still registered to keep Doctrine happy.
    }

    private function getEntityType($entity): ?string
    {
        // Fast-path for real entities.
        if ($entity instanceof CarInventory) return 'Car Inventory';
        if ($entity instanceof Booking) return 'Booking';
        if ($entity instanceof Login) return 'User';
        if ($entity instanceof User) return 'Customer';
        if ($entity instanceof Payment) return 'Payment';

        // Doctrine can hand us proxy/wrapper classes that may not satisfy `instanceof`.
        $baseClass = $this->resolveEntityClassName($entity);

        $entityMap = [
            'App\\Entity\\CarInventory' => 'Car Inventory',
            'App\\Entity\\Booking' => 'Booking',
            'App\\Entity\\Login' => 'User',
            'App\\Entity\\User' => 'Customer',
            'App\\Entity\\Payment' => 'Payment',
        ];

        return $entityMap[$baseClass] ?? null;
    }

    private function resolveEntityClassName(object $entity): string
    {
        $class = get_class($entity);

        // If this is a Doctrine proxy, use its parent entity class.
        if ($entity instanceof Proxy) {
            $parent = get_parent_class($entity);
            if (is_string($parent) && $parent !== '') {
                return $parent;
            }
        }

        // Example proxy class: Proxies\\__CG__\\App\\Entity\\CarInventory
        // Example proxy class: Proxies\\__PM__\\App\\Entity\\CarInventory
        if (str_starts_with($class, 'Proxies\\')) {
            $parent = get_parent_class($entity);
            if (is_string($parent) && $parent !== '') {
                return $parent;
            }
        }

        // Regex fallback: extract the embedded App\\Entity\\<EntityName> portion.
        if (preg_match('/App\\\\Entity\\\\(CarInventory|Booking|Login|User|Payment)/', $class, $m)) {
            return 'App\\Entity\\' . $m[1];
        }

        return $class;
    }

    private function createLogEntry(EntityManagerInterface $em, string $action, string $entityType, ?int $entityId = null): void
    {
        $user = $this->security->getUser();
        $username = $user ? $user->getUserIdentifier() : 'System';
        $roles = $user ? $user->getRoles() : [];
        $role = !empty($roles) ? implode(', ', $roles) : 'ANONYMOUS';

        $log = new ActivityLog();
        $log->setUser($username);
        $log->setRole($role);
        $log->setAction($action);
        $log->setDateTime(new \DateTime('now'));
        $log->setEntityType($entityType);
        $log->setEntityId($entityId);

        $em->persist($log);
    }
}

