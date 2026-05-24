<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\DeviceToken;
use App\Entity\Login;
use App\Repository\DeviceTokenRepository;
use Doctrine\ORM\EntityManagerInterface;

final class DeviceTokenService
{
    private const ALLOWED_PLATFORMS = ['android', 'ios'];

    public function __construct(
        private readonly DeviceTokenRepository $deviceTokenRepository,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function register(Login $login, string $fcmToken, string $platform): void
    {
        $fcmToken = trim($fcmToken);
        if ($fcmToken === '') {
            throw new \InvalidArgumentException('FCM token is required.');
        }

        if (strlen($fcmToken) > 512) {
            throw new \InvalidArgumentException('FCM token is too long.');
        }

        $platform = strtolower(trim($platform));
        if (!\in_array($platform, self::ALLOWED_PLATFORMS, true)) {
            throw new \InvalidArgumentException('Platform must be android or ios.');
        }

        $existing = $this->deviceTokenRepository->findOneByFcmToken($fcmToken);
        if ($existing instanceof DeviceToken) {
            $existing->setLogin($login);
            $existing->setPlatform($platform);
            $existing->touch();
        } else {
            $deviceToken = new DeviceToken();
            $deviceToken->setLogin($login);
            $deviceToken->setFcmToken($fcmToken);
            $deviceToken->setPlatform($platform);
            $this->em->persist($deviceToken);
        }

        $this->em->flush();
    }

    public function remove(Login $login, string $fcmToken): bool
    {
        $fcmToken = trim($fcmToken);
        if ($fcmToken === '') {
            throw new \InvalidArgumentException('FCM token is required.');
        }

        $removed = $this->deviceTokenRepository->deleteForLoginAndToken($login, $fcmToken);
        if ($removed) {
            $this->em->flush();
        }

        return $removed;
    }
}
