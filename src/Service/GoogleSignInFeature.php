<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Whether Google Sign-In buttons should appear (web + driven by env credentials).
 */
final class GoogleSignInFeature
{
    public function __construct(
        private readonly string $googleClientId = '',
        private readonly string $googleClientSecret = '',
    ) {
    }

    public function isEnabled(): bool
    {
        return trim($this->googleClientId) !== '' && trim($this->googleClientSecret) !== '';
    }
}
