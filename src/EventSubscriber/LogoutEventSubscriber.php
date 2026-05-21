<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\Security\Http\Event\LogoutEvent;

/**
 * Ensures logout completely prevents back-button access to protected content.
 */
final class LogoutEventSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            LogoutEvent::class => 'onLogout',
        ];
    }

    public function onLogout(LogoutEvent $event): void
    {
        $response = $event->getResponse();
        $request = $event->getRequest();
        
        // Set aggressive cache-busting headers on logout response
        $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate, private, max-age=0, post-check=0, pre-check=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '-1');
        $response->headers->set('Last-Modified', gmdate('D, d M Y H:i:s T'));
        $response->headers->set('ETag', '"' . uniqid() . '"');
        
        // Explicitly invalidate session
        $session = $request->getSession();
        if ($session->isStarted()) {
            $session->invalidate();
        }
        
        // Clear session cookies
        $response->headers->clearCookie('PHPSESSID', '/', null, false, false);
        $response->headers->clearCookie('SERVERID', '/', null, false, false);
        
        // Also clear any remember-me cookies
        $response->headers->clearCookie('REMEMBERME', '/', null, false, false);
    }
}

