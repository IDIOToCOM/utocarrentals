<?php

namespace App\Twig;

use App\Car\CarCatalogSort;
use App\Car\CatalogAvailabilityQuery;
use App\Car\VehicleTypeChoices;
use App\Entity\Login;
use App\Repository\CarFavoriteRepository;
use App\Service\CarCompareSession;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class CatalogExtension extends AbstractExtension
{
    public function __construct(
        private readonly Security $security,
        private readonly CarFavoriteRepository $favoriteRepository,
        private readonly CarCompareSession $compareSession,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('catalog_vehicle_types', $this->getVehicleTypes(...)),
            new TwigFunction('catalog_query_params', $this->buildCatalogQueryParams(...)),
            new TwigFunction('catalog_availability_params_from_request', $this->catalogAvailabilityParamsFromRequest(...)),
            new TwigFunction('catalog_favorite_count', $this->getFavoriteCount(...)),
            new TwigFunction('catalog_compare_count', $this->getCompareCount(...)),
        ];
    }

    public function getFavoriteCount(): int
    {
        $user = $this->security->getUser();
        if (!$user instanceof Login) {
            return 0;
        }

        if (\in_array('ROLE_ADMIN', $user->getRoles(), true) || \in_array('ROLE_STAFF', $user->getRoles(), true)) {
            return 0;
        }

        return $this->favoriteRepository->countForUser($user->getId());
    }

    public function getCompareCount(): int
    {
        return $this->compareSession->count();
    }

    /**
     * @return list<string>
     */
    public function getVehicleTypes(): array
    {
        return VehicleTypeChoices::ALL;
    }

    /**
     * @param array<string, string> $availabilityParams
     *
     * @return array<string, string>
     */
    public function buildCatalogQueryParams(
        ?string $type,
        ?string $search,
        ?string $sort,
        array $availabilityParams = [],
    ): array {
        return CarCatalogSort::queryParams(
            $type,
            $search,
            $sort ?? CarCatalogSort::DEFAULT,
            $availabilityParams,
        );
    }

    /**
     * @return array<string, string>
     */
    public function catalogAvailabilityParamsFromRequest(): array
    {
        $request = $this->requestStack->getCurrentRequest();
        if ($request === null) {
            return [];
        }

        return CatalogAvailabilityQuery::fromRequest($request)->toQueryParams();
    }
}
