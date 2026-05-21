<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PublicPagesController extends AbstractController
{
    #[Route('/about', name: 'app_about', methods: ['GET'])]
    public function about(): Response
    {
        return $this->render('landing/about.html.twig');
    }

    #[Route('/help', name: 'app_rental_help', methods: ['GET'])]
    public function rentalHelp(): Response
    {
        return $this->render('landing/rental_help.html.twig');
    }

    #[Route('/contact', name: 'app_contact', methods: ['GET'])]
    public function contact(): Response
    {
        $raw = $_ENV['CONTACT_FORM_EMBED_URL'] ?? getenv('CONTACT_FORM_EMBED_URL');
        $embedUrl = \is_string($raw) ? trim($raw) : '';

        return $this->render('landing/contact.html.twig', [
            'contactFormEmbedUrl' => $embedUrl,
        ]);
    }
}
