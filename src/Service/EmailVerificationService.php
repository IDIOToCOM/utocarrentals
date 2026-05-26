<?php

namespace App\Service;

use App\Entity\Login;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

class EmailVerificationService
{
    private const MAIL_TIMEOUT_SECONDS = 5;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private MailerInterface $mailer,
        private string $fromEmail,
        private string $fromName,
    ) {
    }

    public function generateVerificationToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    public function sendVerificationEmail(Login $user, string $verificationUrl): bool
    {
        $to = $user->getEmail();
        if ($to === null || $to === '') {
            return false;
        }

        $email = (new TemplatedEmail())
            ->from(new Address($this->fromEmail, $this->fromName))
            ->to(new Address($to))
            ->subject('Please verify your email address')
            ->htmlTemplate('emails/verification.html.twig')
            ->context([
                'user' => $user,
                'verificationUrl' => $verificationUrl,
            ]);

        return $this->sendWithoutHanging($email);
    }

    public function verifyToken(string $token): ?Login
    {
        $user = $this->entityManager
            ->getRepository(Login::class)
            ->findOneBy(['verificationToken' => $token]);

        if (!$user instanceof Login) {
            return null;
        }

        $user->setIsVerified(true);
        $user->setVerificationToken(null);
        $this->entityManager->flush();

        return $user;
    }

    public function needsVerification(Login $user): bool
    {
        return !$user->isVerified();
    }

    private function sendWithoutHanging(TemplatedEmail $email): bool
    {
        $previousTimeout = ini_get('default_socket_timeout');
        @ini_set('default_socket_timeout', (string) self::MAIL_TIMEOUT_SECONDS);

        try {
            $this->mailer->send($email);

            return true;
        } catch (\Throwable $e) {
            error_log('Failed to send verification email: '.$e->getMessage());

            return false;
        } finally {
            if ($previousTimeout !== false) {
                @ini_set('default_socket_timeout', $previousTimeout);
            }
        }
    }
}
