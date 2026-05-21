<?php

namespace App\EventSubscriber;

use App\Entity\Login;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Logged-in users cannot view public marketing pages; customers go to the vehicle catalog.
 */
final class MarketingPagesAccessSubscriber implements EventSubscriberInterface
{
    private const MARKETING_ROUTES = [
        'app_home',
        'app_about',
        'app_contact',
    ];

    public function __construct(
        private readonly TokenStorageInterface $tokenStorage,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 0],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $route = $request->attributes->get('_route');
        if (!\is_string($route) || !\in_array($route, self::MARKETING_ROUTES, true)) {
            return;
        }

        $token = $this->tokenStorage->getToken();
        $user = $token?->getUser();
        if (!$user instanceof Login) {
            return;
        }

        $roles = $user->getRoles();
        if (\in_array('ROLE_ADMIN', $roles, true) || \in_array('ROLE_STAFF', $roles, true)) {
            $event->setResponse(new RedirectResponse($this->urlGenerator->generate('app_admin')));

            return;
        }

        $event->setResponse(new RedirectResponse($this->urlGenerator->generate('app_car_catalog')));
    }
}
