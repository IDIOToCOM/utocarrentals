<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Validates a Google Sign-In id_token for mobile apps (Firebase / React Native).
 */
final class GoogleIdTokenVerifier
{
    /** @var list<string> */
    private array $allowedClientIds;

    /**
     * @param string $googleClientId     Website OAuth client ID (fallback for mobile aud check)
     * @param string $googleMobileClientId Firebase / SAMSON Web client ID (preferred for mobile)
     */
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        string $googleClientId,
        string $googleMobileClientId = '',
    ) {
        $this->allowedClientIds = array_values(array_unique(array_filter(
            [trim($googleMobileClientId), trim($googleClientId)],
            static fn (string $id): bool => $id !== '',
        )));
    }

    /**
     * @return array{email: string, email_verified: bool|string}
     */
    public function verify(string $idToken): array
    {
        if ($this->allowedClientIds === []) {
            throw new \InvalidArgumentException(
                'Google sign-in is not configured on the server (set GOOGLE_MOBILE_CLIENT_ID or GOOGLE_CLIENT_ID).',
            );
        }

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
        if (!\in_array($aud, $this->allowedClientIds, true)) {
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
