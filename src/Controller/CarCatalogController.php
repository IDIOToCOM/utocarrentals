<?php

namespace App\Controller;

use App\Car\CarCatalogSort;
use App\Car\CatalogAvailabilityQuery;
use App\Car\VehicleTypeChoices;
use App\Repository\CarInventoryRepository;
use App\Entity\Login;
use App\Service\CarCatalogImageResolver;
use App\Service\CarCompareSession;
use App\Service\CatalogAvailabilityFilter;
use App\Service\CatalogRentalEstimateBuilder;
use App\Service\CarFavoriteService;
use App\Service\CarReviewService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[IsGranted('ROLE_USER')]
final class CarCatalogController extends AbstractController
{
    public function __construct(
        private readonly CarInventoryRepository $carInventoryRepository,
        private readonly CarCatalogImageResolver $imageResolver,
        private readonly CarFavoriteService $favoriteService,
        private readonly CarCompareSession $compareSession,
        private readonly CarReviewService $reviewService,
        private readonly CatalogAvailabilityFilter $availabilityFilter,
        private readonly CatalogRentalEstimateBuilder $rentalEstimateBuilder,
    ) {
    }

    #[Route('/cars', name: 'app_car_catalog', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $typeFilter = $request->query->getString('type');
        $typeFilter = $typeFilter !== '' ? $typeFilter : null;

        $search = trim($request->query->getString('q'));
        $search = $search !== '' ? $search : null;

        $sort = CarCatalogSort::normalize($request->query->getString('sort'));

        $availabilityQuery = CatalogAvailabilityQuery::fromRequest($request);
        $availabilityError = $this->availabilityFilter->resolveCatalogError($availabilityQuery);
        $availabilityParams = $availabilityQuery->toQueryParams();
        $availabilityFiltering = $availabilityQuery->isFiltering() && $availabilityError === null;

        $cars = $this->carInventoryRepository->findForCustomerCatalog($typeFilter, $search, $sort);
        if ($availabilityFiltering) {
            $cars = $this->availabilityFilter->filterAvailable($cars, $availabilityQuery);
        }
        $types = VehicleTypeChoices::ALL;

        $carIds = array_values(array_filter(array_map(
            static fn ($c) => $c->getId(),
            $cars,
        )));
        $reviewSummaries = $this->reviewService->getSummariesForCarIds($carIds);

        $showRentalEstimate = $availabilityFiltering;

        $catalogItems = [];
        foreach ($cars as $car) {
            $carId = $car->getId();
            $catalogItems[] = [
                'car' => $car,
                'imageUrl' => $this->imageResolver->resolve($car),
                'reviewSummary' => $reviewSummaries[$carId] ?? $this->reviewService->getSummaryForCar($carId ?? 0),
                'rentalEstimate' => $showRentalEstimate
                    ? $this->rentalEstimateBuilder->fromQuery($availabilityQuery, $car->getPricePerDay())
                    : null,
            ];
        }

        return $this->render('car_catalog/index.html.twig', array_merge(
            $this->catalogBrowseContext(),
            [
                'catalogItems' => $catalogItems,
                'types' => $types,
                'activeType' => $typeFilter,
                'activeSearch' => $search,
                'activeSort' => $sort,
                'sortChoices' => CarCatalogSort::choices(),
                'availabilityQuery' => $availabilityQuery,
                'availabilityParams' => $availabilityParams,
                'availabilityError' => $availabilityError,
                'availabilityFiltering' => $availabilityFiltering,
            ],
        ));
    }

    #[Route('/cars/{id}', name: 'app_car_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(int $id, Request $request): Response
    {
        $car = $this->carInventoryRepository->findAvailableForCustomer($id);
        if ($car === null) {
            throw $this->createNotFoundException('This vehicle is not available, is out of service, or does not exist.');
        }

        $availabilityQuery = CatalogAvailabilityQuery::fromRequest($request);
        $availabilityError = $this->availabilityFilter->resolveCatalogError($availabilityQuery);
        $availabilityParams = $availabilityQuery->toQueryParams();
        $rentalEstimate = $availabilityQuery->isFiltering() && $availabilityError === null
            ? $this->rentalEstimateBuilder->fromQuery($availabilityQuery, $car->getPricePerDay())
            : null;

        $customer = $this->getCustomerAccount();
        $userReview = null;
        $canSubmitReview = false;
        if ($customer !== null) {
            $userReview = $this->reviewService->findUserReview($customer, $id);
            $canSubmitReview = $this->reviewService->canCustomerSubmitOrEdit($customer, $id);
        }

        return $this->render('car_catalog/show.html.twig', array_merge(
            $this->catalogBrowseContext(),
            [
                'car' => $car,
                'imageUrl' => $this->imageResolver->resolve($car),
                'reviewSummary' => $this->reviewService->getSummaryForCar($id),
                'reviews' => $this->reviewService->getReviewsForCar($id),
                'userReview' => $userReview,
                'canSubmitReview' => $canSubmitReview,
                'availabilityParams' => $availabilityParams,
                'rentalEstimate' => $rentalEstimate,
            ],
        ));
    }

    private function getCustomerAccount(): ?Login
    {
        $user = $this->getUser();
        if (!$user instanceof Login) {
            return null;
        }

        if (\in_array('ROLE_ADMIN', $user->getRoles(), true) || \in_array('ROLE_STAFF', $user->getRoles(), true)) {
            return null;
        }

        return $user;
    }

    /**
     * @return array{favoriteCarIds: list<int>, compareCarIds: list<int>, maxCompare: int}
     */
    private function catalogBrowseContext(): array
    {
        $favoriteIds = [];
        $user = $this->getUser();
        if ($user instanceof Login && !\in_array('ROLE_ADMIN', $user->getRoles(), true) && !\in_array('ROLE_STAFF', $user->getRoles(), true)) {
            $favoriteIds = $this->favoriteService->getFavoriteCarIds($user);
        }

        return [
            'favoriteCarIds' => $favoriteIds,
            'compareCarIds' => $this->compareSession->getCarIds(),
            'maxCompare' => CarCompareSession::MAX_CARS,
        ];
    }
}
