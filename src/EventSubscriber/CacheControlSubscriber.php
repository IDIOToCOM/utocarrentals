<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

/**
 * Prevents browser caching of ANY authenticated session to avoid exposing
 * protected content when using browser back button after logout.
 */
final class CacheControlSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly TokenStorageInterface $tokenStorage)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => 'onResponse',
        ];
    }

    public function onResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $response = $event->getResponse();
        
        // ALWAYS apply no-store headers - never cache any authenticated content
        // This prevents the browser back button from showing cached protected pages
        $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate, private, max-age=0, post-check=0, pre-check=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '-1');
        $response->headers->set('Last-Modified', gmdate('D, d M Y H:i:s T'));
        $response->headers->set('ETag', '"' . uniqid() . '"');
    }
}

