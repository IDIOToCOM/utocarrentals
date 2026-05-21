<?php

namespace App\EventSubscriber;

use App\Entity\ActivityLog;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\Security\Http\Event\LogoutEvent;
use Symfony\Component\Security\Http\SecurityEvents;

class LoginLogoutSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            SecurityEvents::INTERACTIVE_LOGIN => 'onInteractiveLogin',
            LogoutEvent::class => 'onLogout',
        ];
    }

    public function onInteractiveLogin(InteractiveLoginEvent $event): void
    {
        // Google OAuth LOGIN is recorded in GoogleStaffAuthenticator::onAuthenticationSuccess
        // (OAuth may not dispatch this event consistently; when it does, skip duplicate rows).
        if ($event->getRequest()->attributes->get('_route') === 'connect_google_check') {
            return;
        }

        try {
            $user = $event->getAuthenticationToken()->getUser();
            $username = $user->getUserIdentifier();
            $roles = $user->getRoles();
            $role = !empty($roles) ? implode(', ', $roles) : 'ROLE_USER';

            $log = new ActivityLog();
            $log->setUser($username);
            $log->setRole($role);
            $log->setAction('LOGIN');
            $log->setDateTime(new \DateTime('now'));
            $log->setEntityType('Authentication');
            $log->setEntityId(null);

            $this->entityManager->persist($log);
            $this->entityManager->flush();
        } catch (\Exception $e) {
            $this->logger->warning('Activity log login failed: {message}', ['message' => $e->getMessage(), 'exception' => $e]);
        }
    }

    public function onLogout(LogoutEvent $event): void
    {
        try {
            $token = $event->getToken();

            if (!$token) {
                $this->logger->notice('Logout activity log skipped: no security token on LogoutEvent.');

                return;
            }

            $user = $token->getUser();
            $username = is_string($user) ? $user : $user->getUserIdentifier();
            $roles = is_string($user) ? [] : $user->getRoles();
            $role = !empty($roles) ? implode(', ', $roles) : 'ROLE_USER';

            $log = new ActivityLog();
            $log->setUser($username);
            $log->setRole($role);
            $log->setAction('LOGOUT');
            $log->setDateTime(new \DateTime('now'));
            $log->setEntityType('Authentication');
            $log->setEntityId(null);

            $this->entityManager->persist($log);
            $this->entityManager->flush();
        } catch (\Exception $e) {
            $this->logger->warning('Activity log logout failed: {message}', ['message' => $e->getMessage(), 'exception' => $e]);
        }
    }
}

