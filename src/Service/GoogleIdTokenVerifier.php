<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Validates a Google Sign-In id_token for mobile apps.
 */
final class GoogleIdTokenVerifier
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $googleClientId,
    ) {
    }

    /**
     * @return array{email: string, email_verified: bool|string}
     */
    public function verify(string $idToken): array
    {
        $idToken = trim($idToken);
        if ($idToken === '') {
            throw new \InvalidArgumentException('Google id token is required.');
        }

        $response = $this->httpClient->request(
            'GET',
            'https://oauth2.googleapis.com/tokeninfo',
            ['query' => ['id_token' => $idToken]],
        );

        if ($response->getStatusCode() !== 200) {
            throw new \InvalidArgumentException('Invalid Google sign-in token.');
        }

        /** @var array<string, mixed> $data */
        $data = $response->toArray(false);

        $aud = (string) ($data['aud'] ?? '');
        if ($aud !== $this->googleClientId) {
            throw new \InvalidArgumentException('Google sign-in token audience mismatch.');
        }

        $verified = $data['email_verified'] ?? false;
        if ($verified !== true && $verified !== 'true') {
            throw new \InvalidArgumentException('Google account email is not verified.');
        }

        $email = $data['email'] ?? null;
        if (!\is_string($email) || trim($email) === '') {
            throw new \InvalidArgumentException('Google account email is missing.');
        }

        return [
            'email' => trim($email),
            'email_verified' => $verified,
        ];
    }
}
