<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Login;
use App\Repository\LoginRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Find or create a customer account from a Google-verified email (web OAuth + mobile id token).
 */
final class GoogleOAuthAccountService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LoginRepository $loginRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function findOrCreateFromGoogleEmail(string $email): Login
    {
        $email = trim($email);
        if ($email === '') {
            throw new \InvalidArgumentException('Google account email is missing.');
        }

        $user = $this->loginRepository->findOneBy(['email' => $email]);
        if ($user instanceof Login) {
            if (!$user->isVerified()) {
                $this->markEmailVerifiedByGoogle($user);
                $this->entityManager->flush();
            }

            return $user;
        }

        $user = new Login();
        $user->setEmail($email);

        $baseUsername = preg_replace('/[^a-zA-Z0-9_]/', '_', (string) strstr($email, '@', true));
        $baseUsername = $baseUsername !== '' ? $baseUsername : 'user';

        $candidate = $baseUsername;
        $i = 1;
        while ($this->loginRepository->findOneBy(['username' => $candidate]) instanceof Login) {
            $candidate = $baseUsername.$i;
            $i++;
        }

        $user->setUsername($candidate);
        $user->setRoles(['ROLE_USER']);
        $this->markEmailVerifiedByGoogle($user);
        $user->setPassword($this->passwordHasher->hashPassword($user, bin2hex(random_bytes(32))));

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    private function markEmailVerifiedByGoogle(Login $user): void
    {
        $user->setIsVerified(true);
        $user->setVerificationToken(null);
    }
}
