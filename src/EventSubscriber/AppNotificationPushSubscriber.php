<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\AppNotification;
use App\Service\FcmPushService;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Events;

/**
 * Sends FCM push after AppNotification rows are flushed (booking events, reminders, etc.).
 */
#[AsDoctrineListener(event: Events::postPersist)]
#[AsDoctrineListener(event: Events::postFlush)]
final class AppNotificationPushSubscriber
{
    /** @var list<AppNotification> */
    private array $pending = [];

    private bool $dispatchScheduled = false;

    public function __construct(
        private readonly FcmPushService $fcmPushService,
    ) {
    }

    public function postPersist(PostPersistEventArgs $args): void
    {
        $entity = $args->getObject();
        if (!$entity instanceof AppNotification) {
            return;
        }

        $this->pending[] = $entity;
    }

    public function postFlush(PostFlushEventArgs $args): void
    {
        if ($this->pending === [] || $this->dispatchScheduled) {
            return;
        }

        $notifications = $this->pending;
        $this->pending = [];
        $this->dispatchScheduled = true;

        try {
            foreach ($notifications as $notification) {
                if ($notification->getId() === null) {
                    continue;
                }
                try {
                    $this->fcmPushService->sendForNotification($notification);
                } catch (\Throwable) {
                    // Never block the request on push failures.
                }
            }
        } finally {
            $this->dispatchScheduled = false;
        }
    }
}
