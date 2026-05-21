<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Login;
use App\Service\GoogleIdTokenVerifier;
use App\Service\GoogleOAuthAccountService;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * JWT login for mobile Google Sign-In (same accounts as website OAuth).
 */
#[Route('/api')]
final class MobileGoogleAuthController extends AbstractController
{
    public function __construct(
        private readonly GoogleIdTokenVerifier $idTokenVerifier,
        private readonly GoogleOAuthAccountService $googleAccounts,
        private readonly JWTTokenManagerInterface $jwtManager,
    ) {
    }

    #[Route('/auth/google', name: 'api_auth_google', methods: ['POST'])]
    public function googleLogin(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!\is_array($data)) {
            return $this->json([
                'message' => 'Invalid JSON body',
            ], Response::HTTP_BAD_REQUEST);
        }

        $idToken = $data['idToken'] ?? $data['id_token'] ?? null;
        if (!\is_string($idToken) || trim($idToken) === '') {
            return $this->json([
                'message' => 'Google id token is required',
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $payload = $this->idTokenVerifier->verify($idToken);
            $user = $this->googleAccounts->findOrCreateFromGoogleEmail($payload['email']);
        } catch (\InvalidArgumentException $e) {
            return $this->json([
                'message' => $e->getMessage(),
            ], Response::HTTP_UNAUTHORIZED);
        }

        if (!$user instanceof Login) {
            return $this->json([
                'message' => 'Could not sign in with Google',
            ], Response::HTTP_UNAUTHORIZED);
        }

        if (!$user->isEnabled()) {
            return $this->json([
                'message' => 'Your account has been disabled. Please contact support.',
            ], Response::HTTP_FORBIDDEN);
        }

        try {
            $token = $this->jwtManager->create($user);
        } catch (\Throwable $e) {
            return $this->json([
                'message' => 'Server could not issue a login token. Ensure JWT keys and JWT_PASSPHRASE are configured on the server.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $this->json([
            'token' => $token,
            'username' => $user->getUserIdentifier(),
            'email' => $user->getEmail(),
        ]);
    }
}
