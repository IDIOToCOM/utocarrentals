<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Login;
use App\MobileApi\MobileApiEnvelope;
use App\Repository\LoginRepository;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Mobile password login (JWT). Website uses session form login at /login — same users, different route.
 */
#[Route('/api/mobile/v1')]
final class MobileAuthController extends AbstractController
{
    public function __construct(
        private readonly LoginRepository $loginRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly JWTTokenManagerInterface $jwtManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route('/auth/login', name: 'api_mobile_v1_auth_login', methods: ['POST'])]
    public function login(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!\is_array($data)) {
            return MobileApiEnvelope::fail('INVALID_JSON', 'Invalid JSON body', Response::HTTP_BAD_REQUEST);
        }

        $identifier = $data['username'] ?? $data['email'] ?? null;
        $password = $data['password'] ?? null;

        if (!\is_string($identifier) || !\is_string($password)) {
            return MobileApiEnvelope::fail(
                'VALIDATION_ERROR',
                'Username or email and password are required',
                Response::HTTP_BAD_REQUEST,
            );
        }

        $identifier = trim($identifier);
        if ($identifier === '' || $password === '') {
            return MobileApiEnvelope::fail(
                'VALIDATION_ERROR',
                'Username or email and password are required',
                Response::HTTP_BAD_REQUEST,
            );
        }

        $user = $this->loginRepository->findOneByIdentifier($identifier);
        if (!$user instanceof Login || !$this->passwordHasher->isPasswordValid($user, $password)) {
            return MobileApiEnvelope::fail(
                'INVALID_CREDENTIALS',
                'Invalid username or password.',
                Response::HTTP_UNAUTHORIZED,
            );
        }

        if (!$user->isEnabled()) {
            return MobileApiEnvelope::fail(
                'ACCOUNT_DISABLED',
                'Your account has been disabled. Please contact support.',
                Response::HTTP_FORBIDDEN,
            );
        }

        try {
            $token = $this->jwtManager->create($user);
        } catch (\Throwable $e) {
            $this->logger->error('Mobile JWT login failed', [
                'user' => $user->getUserIdentifier(),
                'exception' => $e->getMessage(),
            ]);

            return MobileApiEnvelope::fail(
                'JWT_SIGNING_FAILED',
                'Mobile sign-in is not configured on the server (JWT keys). Website login still works — run lexik:jwt:generate-keypair on Forge.',
                Response::HTTP_SERVICE_UNAVAILABLE,
            );
        }

        return MobileApiEnvelope::ok([
            'token' => $token,
            'username' => $user->getUserIdentifier(),
            'email' => $user->getEmail(),
        ]);
    }
}
