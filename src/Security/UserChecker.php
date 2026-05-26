<?php

namespace App\Security;

use App\Entity\Login;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\Exception\DisabledException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class UserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof Login) {
            return;
        }

        if (!$user->isEnabled()) {
            throw new DisabledException('Your account has been disabled. Please contact an administrator.');
        }

        if (!$user->isVerified()) {
            throw new CustomUserMessageAccountStatusException('Please verify your email before signing in. Check your inbox for the verification link.');
        }
    }

    public function checkPostAuth(UserInterface $user): void
    {
        // No post-authentication checks needed
    }
}

