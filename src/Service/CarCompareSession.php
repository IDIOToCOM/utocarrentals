<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

final class CarCompareSession
{
    public const MAX_CARS = 4;

    private const SESSION_KEY = 'car_compare_ids';

    public function __construct(
        private readonly RequestStack $requestStack,
    ) {
    }

    /**
     * @return list<int>
     */
    public function getCarIds(): array
    {
        $session = $this->getSession();
        if ($session === null) {
            return [];
        }

        $ids = $session->get(self::SESSION_KEY, []);
        if (!\is_array($ids)) {
            return [];
        }

        $normalized = [];
        foreach ($ids as $id) {
            $intId = (int) $id;
            if ($intId > 0 && !\in_array($intId, $normalized, true)) {
                $normalized[] = $intId;
            }
        }

        return \array_slice($normalized, 0, self::MAX_CARS);
    }

    public function contains(int $carId): bool
    {
        return \in_array($carId, $this->getCarIds(), true);
    }

    public function count(): int
    {
        return \count($this->getCarIds());
    }

    /**
     * @return 'added'|'removed'|'full'
     */
    public function toggle(int $carId): string
    {
        $ids = $this->getCarIds();
        $index = array_search($carId, $ids, true);

        if ($index !== false) {
            unset($ids[$index]);
            $this->save(array_values($ids));

            return 'removed';
        }

        if (\count($ids) >= self::MAX_CARS) {
            return 'full';
        }

        $ids[] = $carId;
        $this->save($ids);

        return 'added';
    }

    public function remove(int $carId): void
    {
        $ids = array_values(array_filter(
            $this->getCarIds(),
            static fn (int $id): bool => $id !== $carId,
        ));
        $this->save($ids);
    }

    public function clear(): void
    {
        $this->save([]);
    }

    /**
     * @param list<int> $ids
     */
    private function save(array $ids): void
    {
        $session = $this->getSession();
        if ($session !== null) {
            $session->set(self::SESSION_KEY, \array_slice($ids, 0, self::MAX_CARS));
        }
    }

    private function getSession(): ?SessionInterface
    {
        $request = $this->requestStack->getCurrentRequest();
        if ($request === null || !$request->hasSession()) {
            return null;
        }

        return $request->getSession();
    }
}
