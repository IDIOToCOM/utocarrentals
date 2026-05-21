<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class LogoutController extends AbstractController
{
    #[Route('/logout', name: 'app_logout', methods: ['GET'])]
    public function logout(): Response
    {
        // This route is handled by Symfony's logout handler, but we need to ensure
        // a response is returned with proper headers
        $response = new Response();
        
        // Aggressive cache-busting headers
        $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate, private, max-age=0, post-check=0, pre-check=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '-1');
        $response->headers->set('Last-Modified', gmdate('D, d M Y H:i:s T'));
        
        // Clear cookies
        $response->headers->clearCookie('PHPSESSID');
        $response->headers->clearCookie('SERVERID');
        
        // Redirect to login
        return $this->redirectToRoute('app_login');
    }
}
