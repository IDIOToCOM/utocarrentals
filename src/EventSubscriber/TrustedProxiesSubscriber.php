<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class TrustedProxiesSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            // Use a high priority to ensure this runs before other listeners
            KernelEvents::REQUEST => [
                ['onKernelRequest', 100],
            ],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        // Trust all proxies (useful for ngrok, load balancers, etc.)
        // In production, you should specify specific proxy IPs instead of '0.0.0.0/0'
        // This trusts all IPv4 and IPv6 addresses
        $request->setTrustedProxies(
            ['0.0.0.0/0', '::/0'],
            \Symfony\Component\HttpFoundation\Request::HEADER_X_FORWARDED_FOR
            | \Symfony\Component\HttpFoundation\Request::HEADER_X_FORWARDED_HOST
            | \Symfony\Component\HttpFoundation\Request::HEADER_X_FORWARDED_PORT
            | \Symfony\Component\HttpFoundation\Request::HEADER_X_FORWARDED_PROTO
        );
    }
}

