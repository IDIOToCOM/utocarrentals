<?php

namespace App\Twig;

use App\Entity\AppNotification;
use App\Entity\Login;
use App\Repository\AppNotificationRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class NotificationExtension extends AbstractExtension
{
    public function __construct(
        private readonly Security $security,
        private readonly AppNotificationRepository $notificationRepository,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('notification_unread_count', $this->getUnreadCount(...)),
            new TwigFunction('notification_recent', $this->getRecent(...)),
            new TwigFunction('notification_url', $this->getNotificationUrl(...)),
        ];
    }

    public function getUnreadCount(): int
    {
        $user = $this->security->getUser();
        if (!$user instanceof Login || $user->getId() === null) {
            return 0;
        }

        return $this->notificationRepository->countUnreadForUser((int) $user->getId());
    }

    /**
     * @return list<AppNotification>
     */
    public function getRecent(int $limit = 6): array
    {
        $user = $this->security->getUser();
        if (!$user instanceof Login || $user->getId() === null) {
            return [];
        }

        return $this->notificationRepository->findRecentForUser((int) $user->getId(), $limit);
    }

    public function getNotificationUrl(AppNotification $notification): ?string
    {
        $route = $notification->getLinkRoute();
        if ($route === null) {
            return null;
        }

        try {
            return $this->urlGenerator->generate($route, $notification->getLinkParams() ?? []);
        } catch (\Throwable) {
            return null;
        }
    }
}
