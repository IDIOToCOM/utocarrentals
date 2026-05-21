<?php

namespace App\Car;

final class CarCatalogSort
{
    public const DEFAULT = 'price_asc';

    public const PRICE_ASC = 'price_asc';

    public const PRICE_DESC = 'price_desc';

    public const NAME_ASC = 'name_asc';

    /** @var list<string> */
    private const ALLOWED = [
        self::PRICE_ASC,
        self::PRICE_DESC,
        self::NAME_ASC,
    ];

    /**
     * @return array<string, string> label => value
     */
    public static function choices(): array
    {
        return [
            'Price: low to high' => self::PRICE_ASC,
            'Price: high to low' => self::PRICE_DESC,
            'Name: A–Z' => self::NAME_ASC,
        ];
    }

    public static function normalize(?string $sort): string
    {
        $sort = $sort !== null ? trim($sort) : '';

        return \in_array($sort, self::ALLOWED, true) ? $sort : self::DEFAULT;
    }

    /**
     * @param array<string, string> $extra e.g. availability window from {@see CatalogAvailabilityQuery}
     *
     * @return array<string, string> query params for path('app_car_catalog', ...)
     */
    public static function queryParams(?string $type, ?string $search, string $sort, array $extra = []): array
    {
        $params = [];
        if ($type !== null && $type !== '') {
            $params['type'] = $type;
        }
        if ($search !== null && $search !== '') {
            $params['q'] = $search;
        }
        $sort = self::normalize($sort);
        if ($sort !== self::DEFAULT) {
            $params['sort'] = $sort;
        }

        return array_merge($params, $extra);
    }
}
