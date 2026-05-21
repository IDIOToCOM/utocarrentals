<?php

namespace App\Controller;

use App\Entity\Login;
use App\Repository\AppNotificationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class NotificationController extends AbstractController
{
    public function __construct(
        private readonly AppNotificationRepository $notificationRepository,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    #[Route('/notifications', name: 'app_notifications', methods: ['GET'])]
    public function index(): Response
    {
        $user = $this->requireLogin();

        return $this->render($this->templateForUser($user), [
            'notifications' => $this->notificationRepository->findAllForUser((int) $user->getId()),
            'unreadCount' => $this->notificationRepository->countUnreadForUser((int) $user->getId()),
            'notification_links' => $this->buildLinkMap($user),
        ]);
    }

    #[Route('/notifications/{id}/read', name: 'app_notification_read', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function markRead(int $id, Request $request): Response
    {
        $user = $this->requireLogin();

        if (!$this->isCsrfTokenValid('notification_read_'.$id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid security token.');
        }

        $notification = $this->notificationRepository->findOneForUser($id, (int) $user->getId());
        if ($notification !== null && !$notification->isRead()) {
            $notification->markRead();
            $this->notificationRepository->getEntityManager()->flush();
        }

        $redirect = $request->request->getString('redirect');
        if ($redirect !== '' && str_starts_with($redirect, '/')) {
            return $this->redirect($redirect);
        }

        return $this->redirectToRoute('app_notifications');
    }

    #[Route('/notifications/read-all', name: 'app_notifications_read_all', methods: ['POST'])]
    public function markAllRead(Request $request): Response
    {
        $user = $this->requireLogin();

        if (!$this->isCsrfTokenValid('notifications_read_all', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid security token.');
        }

        $this->notificationRepository->markAllReadForUser((int) $user->getId());
        $this->notificationRepository->getEntityManager()->flush();

        $unreadCount = $this->notificationRepository->countUnreadForUser((int) $user->getId());

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse([
                'ok' => true,
                'unreadCount' => $unreadCount,
            ]);
        }

        $redirect = $request->request->getString('redirect');
        if ($redirect !== '' && str_starts_with($redirect, '/')) {
            return $this->redirect($redirect);
        }

        $referer = $request->headers->get('referer');
        if (\is_string($referer) && $referer !== '' && str_starts_with($referer, $request->getSchemeAndHttpHost())) {
            return $this->redirect($referer);
        }

        return $this->redirectToRoute('app_notifications');
    }

    private function requireLogin(): Login
    {
        $user = $this->getUser();
        if (!$user instanceof Login) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }

    private function templateForUser(Login $user): string
    {
        if ($this->isGranted('ROLE_ADMIN') || $this->isGranted('ROLE_STAFF')) {
            return 'notification/index_admin.html.twig';
        }

        return 'notification/index_customer.html.twig';
    }

    /**
     * @return array<int, string|null>
     */
    private function buildLinkMap(Login $user): array
    {
        $notifications = $this->notificationRepository->findAllForUser((int) $user->getId());
        $map = [];
        foreach ($notifications as $notification) {
            $id = $notification->getId();
            if ($id === null) {
                continue;
            }
            $route = $notification->getLinkRoute();
            if ($route === null) {
                $map[$id] = null;
                continue;
            }
            try {
                $map[$id] = $this->urlGenerator->generate($route, $notification->getLinkParams() ?? []);
            } catch (\Throwable) {
                $map[$id] = null;
            }
        }

        return $map;
    }
}
