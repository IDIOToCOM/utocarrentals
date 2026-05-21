<?php

namespace App\Security;

use App\Entity\Login;
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

        // Email verification is optional; login uses username/email + password only.
    }

    public function checkPostAuth(UserInterface $user): void
    {
        // No post-authentication checks needed
    }
}

