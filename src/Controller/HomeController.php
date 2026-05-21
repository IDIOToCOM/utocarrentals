<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    /**
     * Public landing page (Requirement #1). Staff may use header links to reach the app.
     */
    #[Route('/', name: 'app_home')]
    public function index(): Response
    {
        return $this->render('landing/index.html.twig');
    }
}
