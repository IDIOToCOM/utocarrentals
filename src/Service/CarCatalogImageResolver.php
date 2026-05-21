<?php

namespace App\Service;

use App\Entity\CarInventory;

/**
 * @deprecated Use CarPhotoUploadService::resolvePublicUrl — kept for backward compatibility.
 */
final class CarCatalogImageResolver
{
    public function __construct(
        private readonly CarPhotoUploadService $photoUploadService,
    ) {
    }

    public function resolve(CarInventory $car): string
    {
        return $this->photoUploadService->resolvePublicUrl($car);
    }
}
