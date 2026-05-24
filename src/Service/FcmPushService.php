<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\AppNotification;
use App\Entity\Login;
use App\Repository\DeviceTokenRepository;
use Doctrine\ORM\EntityManagerInterface;
use Google\Auth\Credentials\ServiceAccountCredentials;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Sends Firebase Cloud Messaging (HTTP v1) push notifications to SAMSON devices.
 */
final class FcmPushService
{
    private const FCM_SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    private ?string $cachedAccessToken = null;

    private int $cachedAccessTokenExpiresAt = 0;

    public function __construct(
        private readonly DeviceTokenRepository $deviceTokenRepository,
        private readonly EntityManagerInterface $em,
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly string $fcmProjectId = '',
        private readonly string $fcmServiceAccountJsonPath = '',
    ) {
    }

    public function isConfigured(): bool
    {
        return trim($this->fcmProjectId) !== ''
            && trim($this->fcmServiceAccountJsonPath) !== ''
            && is_readable($this->fcmServiceAccountJsonPath);
    }

    public function sendForNotification(AppNotification $notification): void
    {
        if (!$this->isConfigured()) {
            return;
        }

        $recipient = $notification->getRecipient();
        if (!$recipient instanceof Login || $recipient->getId() === null) {
            return;
        }

        $this->sendToUser(
            $recipient,
            $notification->getTitle(),
            $notification->getBody(),
            $this->buildDataPayload($notification),
        );
    }

    /**
     * @param array<string, string> $data
     */
    public function sendToUser(Login $user, string $title, string $body, array $data = []): void
    {
        if (!$this->isConfigured() || $user->getId() === null) {
            return;
        }

        $tokens = $this->deviceTokenRepository->findByLoginId((int) $user->getId());
        if ($tokens === []) {
            return;
        }

        $accessToken = $this->getAccessToken();
        if ($accessToken === null) {
            return;
        }

        foreach ($tokens as $deviceToken) {
            $this->sendToToken(
                $accessToken,
                $deviceToken->getFcmToken(),
                $title,
                $body,
                $data,
                $deviceToken,
            );
        }
    }

    /**
     * @param array<string, string> $data
     */
    private function sendToToken(
        string $accessToken,
        string $fcmToken,
        string $title,
        string $body,
        array $data,
        ?\App\Entity\DeviceToken $deviceTokenEntity = null,
    ): void {
        $url = sprintf(
            'https://fcm.googleapis.com/v1/projects/%s/messages:send',
            rawurlencode(trim($this->fcmProjectId)),
        );

        $payload = [
            'message' => [
                'token' => $fcmToken,
                'notification' => [
                    'title' => $title,
                    'body' => $body,
                ],
                'data' => $data,
                'android' => [
                    'priority' => 'HIGH',
                ],
            ],
        ];

        try {
            $response = $this->httpClient->request('POST', $url, [
                'headers' => [
                    'Authorization' => 'Bearer '.$accessToken,
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
                'timeout' => 10,
            ]);

            $status = $response->getStatusCode();
            if ($status >= 200 && $status < 300) {
                return;
            }

            $responseBody = $response->getContent(false);
            $this->handleSendFailure($fcmToken, $status, $responseBody, $deviceTokenEntity);
        } catch (\Throwable $e) {
            $this->logger->warning('FCM send failed', [
                'tokenPrefix' => substr($fcmToken, 0, 12),
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function handleSendFailure(
        string $fcmToken,
        int $status,
        string $responseBody,
        ?\App\Entity\DeviceToken $deviceTokenEntity,
    ): void {
        $this->logger->warning('FCM API error', [
            'status' => $status,
            'tokenPrefix' => substr($fcmToken, 0, 12),
            'body' => substr($responseBody, 0, 500),
        ]);

        if (!$this->isStaleTokenError($status, $responseBody)) {
            return;
        }

        if ($deviceTokenEntity instanceof \App\Entity\DeviceToken) {
            $this->em->remove($deviceTokenEntity);
            $this->em->flush();

            return;
        }

        $existing = $this->deviceTokenRepository->findOneByFcmToken($fcmToken);
        if ($existing instanceof \App\Entity\DeviceToken) {
            $this->em->remove($existing);
            $this->em->flush();
        }
    }

    private function isStaleTokenError(int $status, string $responseBody): bool
    {
        if ($status === 404) {
            return true;
        }

        $upper = strtoupper($responseBody);

        return str_contains($upper, 'NOT_FOUND')
            || str_contains($upper, 'UNREGISTERED');
    }

  /**
   * @return array<string, string>
   */
    private function buildDataPayload(AppNotification $notification): array
    {
        $data = [
            'type' => $notification->getType(),
            'notificationId' => (string) ($notification->getId() ?? ''),
        ];

        $booking = $notification->getBooking();
        if ($booking !== null && $booking->getId() !== null) {
            $data['bookingId'] = (string) $booking->getId();
        }

        return $data;
    }

    private function getAccessToken(): ?string
    {
        $now = time();
        if ($this->cachedAccessToken !== null && $this->cachedAccessTokenExpiresAt > ($now + 60)) {
            return $this->cachedAccessToken;
        }

        try {
            $credentials = new ServiceAccountCredentials(
                self::FCM_SCOPE,
                $this->fcmServiceAccountJsonPath,
            );
            $token = $credentials->fetchAuthToken();
            $accessToken = $token['access_token'] ?? null;
            if (!\is_string($accessToken) || $accessToken === '') {
                $this->logger->error('FCM service account did not return an access token');

                return null;
            }

            $this->cachedAccessToken = $accessToken;
            $this->cachedAccessTokenExpiresAt = $now + (int) ($token['expires_in'] ?? 3600);

            return $accessToken;
        } catch (\Throwable $e) {
            $this->logger->error('FCM access token fetch failed', ['error' => $e->getMessage()]);

            return null;
        }
    }
}
