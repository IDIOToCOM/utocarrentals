<?php

declare(strict_types=1);

namespace App\Twig;

use App\Service\GoogleSignInFeature;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

final class GoogleSignInExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(
        private readonly GoogleSignInFeature $googleSignIn,
    ) {
    }

    public function getGlobals(): array
    {
        return [
            'show_google_sign_in_ui' => $this->googleSignIn->isEnabled(),
        ];
    }
}
