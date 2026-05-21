<?php

namespace App\Twig;

use App\Support\UtoSupportContact;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class SupportExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('uto_support_email', $this->getEmail(...)),
            new TwigFunction('uto_support_phone', $this->getPhoneDisplay(...)),
            new TwigFunction('uto_support_phone_tel', $this->getPhoneTel(...)),
        ];
    }

    public function getEmail(): string
    {
        return UtoSupportContact::EMAIL;
    }

    public function getPhoneDisplay(): string
    {
        return UtoSupportContact::PHONE_DISPLAY;
    }

    public function getPhoneTel(): string
    {
        return UtoSupportContact::PHONE_TEL;
    }
}
