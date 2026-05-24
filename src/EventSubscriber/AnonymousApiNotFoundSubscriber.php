<?php

namespace App\EventSubscriber;

use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Hides JSON/API routes from anonymous users as 404 (instead of 401/403 or API Platform entry).
 */
final class AnonymousApiNotFoundSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly Security $security)
    {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();

        if (!str_starts_with($path, '/api')) {
            return;
        }

        if ($this->security->getUser() !== null) {
            return;
        }

        if ($this->isPublicApiPath($path)) {
            return;
        }

        $event->setResponse(new JsonResponse(
            [
                'error' => 'Not Found',
                'message' => 'The requested resource was not found.',
            ],
            JsonResponse::HTTP_NOT_FOUND,
            ['Content-Type' => 'application/json; charset=UTF-8']
        ));
    }

    private function isPublicApiPath(string $path): bool
    {
        $publicExact = [
            '/api/login',
            '/api/register',
            '/api/auth/google',
            '/api/verify-email',
            '/api/resend-verification',
            '/api/verification-status',
        ];

        foreach ($publicExact as $exact) {
            if ($path === $exact) {
                return true;
            }
        }

        return str_starts_with($path, '/api/mobile');
    }

    public static function getSubscribedEvents(): array
    {
        // After firewall (priority 8) so token / session is resolved
        return [KernelEvents::REQUEST => ['onKernelRequest', 5]];
    }
}
