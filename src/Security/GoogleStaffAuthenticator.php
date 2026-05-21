<?php

namespace App\Security;

use App\Entity\ActivityLog;
use App\Entity\Login;
use App\Service\GoogleOAuthAccountService;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use KnpU\OAuth2ClientBundle\Security\Authenticator\OAuth2Authenticator;
use League\OAuth2\Client\Provider\GoogleUser;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

final class GoogleStaffAuthenticator extends OAuth2Authenticator
{
    public function __construct(
        private readonly ClientRegistry $clientRegistry,
        private readonly EntityManagerInterface $entityManager,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly GoogleOAuthAccountService $googleAccounts,
    ) {
    }

    public function supports(Request $request): ?bool
    {
        return $request->attributes->get('_route') === 'connect_google_check';
    }

    public function authenticate(Request $request): Passport
    {
        $client = $this->clientRegistry->getClient('google_staff');
        $accessToken = $this->fetchAccessToken($client);

        return new SelfValidatingPassport(
            new UserBadge('google_staff', function () use ($accessToken, $client): UserInterface {
                /** @var GoogleUser $googleUser */
                $googleUser = $client->fetchUserFromToken($accessToken);

                $email = $googleUser->getEmail();
                if (!\is_string($email) || trim($email) === '') {
                    throw new AuthenticationException('Google account email is missing.');
                }
                return $this->googleAccounts->findOrCreateFromGoogleEmail($email);
            })
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?RedirectResponse
    {
        // Google OAuth may not trigger SecurityEvents::INTERACTIVE_LOGIN like form login; mirror LoginLogoutSubscriber.
        $user = $token->getUser();
        if ($user instanceof Login) {
            try {
                $roles = $user->getRoles();
                $role = !empty($roles) ? implode(', ', $roles) : 'ROLE_USER';

                $log = new ActivityLog();
                $log->setUser($user->getUserIdentifier());
                $log->setRole($role);
                $log->setAction('LOGIN');
                $log->setDateTime(new \DateTime('now'));
                $log->setEntityType('Authentication');
                $log->setEntityId(null);

                $this->entityManager->persist($log);
                $this->entityManager->flush();
            } catch (\Throwable) {
                // Do not block redirect if logging fails
            }
        }

        if ($user instanceof Login) {
            $roles = $user->getRoles();
            if (\in_array('ROLE_ADMIN', $roles, true) || \in_array('ROLE_STAFF', $roles, true)) {
                return new RedirectResponse($this->urlGenerator->generate('app_admin'));
            }
        }

        return new RedirectResponse($this->urlGenerator->generate('app_car_catalog'));
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?RedirectResponse
    {
        if ($exception instanceof CustomUserMessageAccountStatusException) {
            $request->getSession()->getFlashBag()->add('error', $exception->getMessageKey());
        } else {
            $request->getSession()->getFlashBag()->add('error', 'Google login failed.');
        }
        return new RedirectResponse($this->urlGenerator->generate('app_login'));
    }

}

