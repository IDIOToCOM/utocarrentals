<?php

namespace App\Controller;

use App\Entity\Login;
use App\Repository\CarFavoriteRepository;
use App\Service\CarCatalogImageResolver;
use App\Service\CarCompareSession;
use App\Service\CarFavoriteService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
#[Route('/my/favorites')]
final class CarFavoriteController extends AbstractController
{
    public function __construct(
        private readonly CarFavoriteService $favoriteService,
        private readonly CarFavoriteRepository $favoriteRepository,
        private readonly CarCatalogImageResolver $imageResolver,
        private readonly CarCompareSession $compareSession,
    ) {
    }

    #[Route('', name: 'app_my_favorites', methods: ['GET'])]
    public function index(): Response
    {
        $user = $this->requireCustomer();

        $favorites = $this->favoriteRepository->findForUserOrdered($user);
        $items = [];
        foreach ($favorites as $favorite) {
            $car = $favorite->getCar();
            if ($car === null || !$car->isVisibleToCustomers()) {
                continue;
            }
            $items[] = [
                'car' => $car,
                'imageUrl' => $this->imageResolver->resolve($car),
                'savedAt' => $favorite->getCreatedAt(),
            ];
        }

        return $this->render('car_catalog/favorites.html.twig', [
            'items' => $items,
            'favoriteCarIds' => $this->favoriteService->getFavoriteCarIds($user),
            'compareCarIds' => $this->compareSession->getCarIds(),
            'maxCompare' => CarCompareSession::MAX_CARS,
        ]);
    }

    #[Route('/toggle/{id}', name: 'app_car_favorite_toggle', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function toggle(Request $request, int $id, EntityManagerInterface $em): Response
    {
        $user = $this->requireCustomer();

        if (!$this->isCsrfTokenValid('car_favorite_'.$id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid security token.');
        }

        try {
            $result = $this->favoriteService->toggle($user, $id, $em);
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->redirectBack($request);
        }

        if ($result === 'added') {
            $this->addFlash('success', 'Vehicle saved to your favorites.');
        } else {
            $this->addFlash('info', 'Vehicle removed from favorites.');
        }

        return $this->redirectBack($request);
    }

    private function requireCustomer(): Login
    {
        $user = $this->getUser();
        if (!$user instanceof Login) {
            throw $this->createAccessDeniedException();
        }

        if (\in_array('ROLE_ADMIN', $user->getRoles(), true) || \in_array('ROLE_STAFF', $user->getRoles(), true)) {
            throw $this->createAccessDeniedException('Use the admin panel for staff accounts.');
        }

        return $user;
    }

    private function redirectBack(Request $request): Response
    {
        $target = $request->request->get('_redirect');
        if (\is_string($target) && $target !== '' && str_starts_with($target, '/')) {
            return $this->redirect($target);
        }

        $referer = $request->headers->get('referer');
        if (\is_string($referer) && $referer !== '') {
            return $this->redirect($referer);
        }

        return $this->redirectToRoute('app_car_catalog');
    }
}
