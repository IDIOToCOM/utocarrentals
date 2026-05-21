<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\Login;
use App\Repository\LoginRepository;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * Loads users by username or email (same as the website login form).
 */
final class LoginUserProvider implements UserProviderInterface
{
    public function __construct(
        private readonly LoginRepository $loginRepository,
    ) {
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        $user = $this->loginRepository->findOneByIdentifier($identifier);
        if (!$user instanceof Login) {
            throw new UserNotFoundException(sprintf('User "%s" not found.', $identifier));
        }

        return $user;
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof Login) {
            throw new UnsupportedUserException(sprintf('Invalid user class "%s".', $user::class));
        }

        return $this->loadUserByIdentifier($user->getUserIdentifier());
    }

    public function supportsClass(string $class): bool
    {
        return Login::class === $class || is_subclass_of($class, Login::class);
    }
}
