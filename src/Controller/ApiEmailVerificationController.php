<?php

namespace App\Controller;

use App\Entity\Login;
use App\Service\EmailVerificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;  

#[Route('/api')]
final class ApiEmailVerificationController extends AbstractController
{
    public function __construct(
        private readonly EmailVerificationService $emailVerificationService,
        private readonly EntityManagerInterface $entityManager,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    #[Route('/verify-email', name: 'api_verify_email', methods: ['POST'])]
    public function verifyEmail(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!\is_array($data)) {
            return $this->json(['success' => false, 'message' => 'Invalid JSON body'], 400);
        }

        $token = $data['token'] ?? null;
        if (!\is_string($token) || $token === '') {
            return $this->json(['success' => false, 'message' => 'Verification token is required'], 400);
        }

        $user = $this->emailVerificationService->verifyToken($token);
        if (!$user instanceof Login) {
            return $this->json(['success' => false, 'message' => 'Invalid or expired verification token'], 400);
        }

        return $this->json([
            'success' => true,
            'message' => 'Email verified successfully',
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'isVerified' => $user->isVerified(),
            ],
        ], 200);
    }

    #[Route('/resend-verification', name: 'api_resend_verification', methods: ['POST'])]
    public function resendVerification(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!\is_array($data)) {
            return $this->json(['success' => false, 'message' => 'Invalid JSON body'], 400);
        }

        $email = $data['email'] ?? null;
        if (!\is_string($email) || trim($email) === '') {
            return $this->json(['success' => false, 'message' => 'Email is required'], 400);
        }

        $user = $this->entityManager->getRepository(Login::class)->findOneBy(['email' => trim($email)]);
        if (!$user instanceof Login) {
            // Avoid leaking existence; still return success-like response
            return $this->json([
                'success' => true,
                'message' => 'If an account exists, a verification email has been sent.',
            ], 200);
        }

        if ($user->isVerified()) {
            return $this->json(['success' => false, 'message' => 'Email is already verified'], 400);
        }

        $verificationToken = $this->emailVerificationService->generateVerificationToken();
        $user->setVerificationToken($verificationToken);
        $this->entityManager->flush();

        $verificationUrl = $this->urlGenerator->generate(
            'app_verify_email',
            ['token' => $verificationToken],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        if (!$this->emailVerificationService->sendVerificationEmail($user, $verificationUrl)) {
            return $this->json(['success' => false, 'message' => 'Could not send verification email right now. Please try again later.'], 503);
        }

        return $this->json(['success' => true, 'message' => 'Verification email sent successfully'], 200);
    }

    #[Route('/verification-status', name: 'api_verification_status', methods: ['POST'])]
    public function verificationStatus(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!\is_array($data)) {
            return $this->json(['success' => false, 'message' => 'Invalid JSON body'], 400);
        }

        $email = $data['email'] ?? null;
        if (!\is_string($email) || trim($email) === '') {
            return $this->json(['success' => false, 'message' => 'Email is required'], 400);
        }

        $user = $this->entityManager->getRepository(Login::class)->findOneBy(['email' => trim($email)]);
        if (!$user instanceof Login) {
            return $this->json(['success' => false, 'message' => 'User not found'], 404);
        }

        return $this->json([
            'success' => true,
            'isVerified' => $user->isVerified(),
            'email' => $user->getEmail(),
        ], 200);
    }
}

