<?php

namespace App\Car;

use Symfony\Component\HttpFoundation\Request;

/**
 * Rental window from catalog query string (pickup/return date + time).
 */
final class CatalogAvailabilityQuery
{
    public const PARAM_PICKUP_DATE = 'pickupDate';

    public const PARAM_RETURN_DATE = 'returnDate';

    public const PARAM_PICKUP_TIME = 'pickupTime';

    public const PARAM_RETURN_TIME = 'returnTime';

    private function __construct(
        public readonly ?string $pickupDate,
        public readonly ?string $returnDate,
        public readonly ?string $pickupTime,
        public readonly ?string $returnTime,
    ) {
    }

    public static function fromRequest(Request $request): self
    {
        return new self(
            self::trimOrNull($request->query->getString(self::PARAM_PICKUP_DATE)),
            self::trimOrNull($request->query->getString(self::PARAM_RETURN_DATE)),
            self::trimOrNull($request->query->getString(self::PARAM_PICKUP_TIME)),
            self::trimOrNull($request->query->getString(self::PARAM_RETURN_TIME)),
        );
    }

    public function isFiltering(): bool
    {
        return $this->pickupDate !== null
            && $this->returnDate !== null
            && $this->pickupTime !== null
            && $this->returnTime !== null;
    }

    public function hasPartialInput(): bool
    {
        $values = [$this->pickupDate, $this->returnDate, $this->pickupTime, $this->returnTime];

        return array_filter($values, static fn (?string $v): bool => $v !== null) !== []
            && !$this->isFiltering();
    }

    /**
     * @return array<string, string>
     */
    public function toQueryParams(): array
    {
        if (!$this->isFiltering()) {
            return [];
        }

        return [
            self::PARAM_PICKUP_DATE => $this->pickupDate,
            self::PARAM_RETURN_DATE => $this->returnDate,
            self::PARAM_PICKUP_TIME => $this->pickupTime,
            self::PARAM_RETURN_TIME => $this->returnTime,
        ];
    }

    private static function trimOrNull(string $value): ?string
    {
        $value = trim($value);

        return $value !== '' ? $value : null;
    }
}
