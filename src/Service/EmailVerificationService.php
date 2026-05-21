<?php

namespace App\Service;

use App\Entity\Login;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

class EmailVerificationService
{
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

    public function sendVerificationEmail(Login $user, string $verificationUrl): void
    {
        $to = $user->getEmail();
        if ($to === null || $to === '') {
            return;
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

        $this->mailer->send($email);
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
}
