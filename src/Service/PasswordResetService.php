<?php

namespace App\Service;

use App\Entity\Login;
use App\Repository\LoginRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class PasswordResetService
{
    private const TOKEN_BYTES = 32;
    private const TOKEN_TTL_HOURS = 1;

    public function __construct(
        private readonly LoginRepository $loginRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly MailerInterface $mailer,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly string $fromEmail,
        private readonly string $fromName,
    ) {
    }

    /**
     * Does not reveal whether the email exists in the system.
     */
    public function requestReset(string $email): void
    {
        $email = trim($email);
        if ($email === '') {
            return;
        }

        $user = $this->loginRepository->findOneBy(['email' => $email]);
        if (!$user instanceof Login || !$user->isEnabled()) {
            return;
        }

        $token = bin2hex(random_bytes(self::TOKEN_BYTES));
        $user->setResetToken($token);
        $user->setResetTokenExpiresAt(new \DateTimeImmutable('+'.self::TOKEN_TTL_HOURS.' hours'));
        $this->entityManager->flush();

        $to = $user->getEmail();
        if ($to === null || $to === '') {
            return;
        }

        $resetUrl = $this->urlGenerator->generate(
            'app_reset_password',
            ['token' => $token],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        $message = (new TemplatedEmail())
            ->from(new Address($this->fromEmail, $this->fromName))
            ->to(new Address($to))
            ->subject('Reset your Uto password')
            ->htmlTemplate('emails/password_reset.html.twig')
            ->context([
                'user' => $user,
                'resetUrl' => $resetUrl,
                'expiresHours' => self::TOKEN_TTL_HOURS,
            ]);

        try {
            $this->mailer->send($message);
        } catch (\Throwable $e) {
            error_log('Failed to send password reset email: '.$e->getMessage());
        }
    }

    public function findValidUserByToken(string $token): ?Login
    {
        $token = trim($token);
        if ($token === '') {
            return null;
        }

        $user = $this->loginRepository->findOneBy(['resetToken' => $token]);
        if (!$user instanceof Login) {
            return null;
        }

        $expiresAt = $user->getResetTokenExpiresAt();
        if ($expiresAt === null || $expiresAt < new \DateTimeImmutable()) {
            return null;
        }

        return $user;
    }

    public function clearResetToken(Login $user): void
    {
        $user->setResetToken(null);
        $user->setResetTokenExpiresAt(null);
    }
}
