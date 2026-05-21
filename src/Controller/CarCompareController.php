<?php

namespace App\Controller;

use App\Repository\CarInventoryRepository;
use App\Entity\Login;
use App\Service\CarCatalogImageResolver;
use App\Service\CarCompareSession;
use App\Service\CarFavoriteService;
use App\Service\CarReviewService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class CarCompareController extends AbstractController
{
    public function __construct(
        private readonly CarCompareSession $compareSession,
        private readonly CarInventoryRepository $carInventoryRepository,
        private readonly CarCatalogImageResolver $imageResolver,
        private readonly CarFavoriteService $favoriteService,
        private readonly CarReviewService $reviewService,
    ) {
    }

    #[Route('/cars/compare', name: 'app_car_compare', methods: ['GET'])]
    public function index(): Response
    {
        $ids = $this->compareSession->getCarIds();
        $cars = $this->carInventoryRepository->findAvailableForCustomerByIds($ids);

        $carIds = array_map(static fn ($c) => $c->getId(), $cars);
        $reviewSummaries = $this->reviewService->getSummariesForCarIds($carIds);

        $catalogItems = [];
        foreach ($cars as $car) {
            $carId = $car->getId();
            $catalogItems[] = [
                'car' => $car,
                'imageUrl' => $this->imageResolver->resolve($car),
                'reviewSummary' => $reviewSummaries[$carId] ?? $this->reviewService->getSummaryForCar($carId ?? 0),
            ];
        }

        $favoriteIds = [];
        $user = $this->getUser();
        if ($user instanceof Login && !\in_array('ROLE_ADMIN', $user->getRoles(), true) && !\in_array('ROLE_STAFF', $user->getRoles(), true)) {
            $favoriteIds = $this->favoriteService->getFavoriteCarIds($user);
        }

        return $this->render('car_catalog/compare.html.twig', [
            'catalogItems' => $catalogItems,
            'compareCarIds' => $this->compareSession->getCarIds(),
            'favoriteCarIds' => $favoriteIds,
            'maxCompare' => CarCompareSession::MAX_CARS,
        ]);
    }

    #[Route('/cars/compare/toggle/{id}', name: 'app_car_compare_toggle', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function toggle(Request $request, int $id): Response
    {
        if (!$this->isCsrfTokenValid('car_compare_'.$id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid security token.');
        }

        $car = $this->carInventoryRepository->findAvailableForCustomer($id);
        if ($car === null) {
            $this->addFlash('error', 'This vehicle is not available to compare.');

            return $this->redirectBack($request);
        }

        $result = $this->compareSession->toggle($id);
        if ($result === 'added') {
            $this->addFlash('success', 'Added to compare list.');
        } elseif ($result === 'removed') {
            $this->addFlash('info', 'Removed from compare list.');
        } else {
            $this->addFlash('error', sprintf('You can compare up to %d vehicles at once. Remove one first.', CarCompareSession::MAX_CARS));
        }

        return $this->redirectBack($request);
    }

    #[Route('/cars/compare/clear', name: 'app_car_compare_clear', methods: ['POST'])]
    public function clear(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('car_compare_clear', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid security token.');
        }

        $this->compareSession->clear();
        $this->addFlash('info', 'Compare list cleared.');

        return $this->redirectToRoute('app_car_catalog');
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
