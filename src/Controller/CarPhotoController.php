<?php

namespace App\Controller;

use App\Service\CarPhotoUploadService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Serves uploaded car photos from persistent storage (Forge-safe; no symlink required).
 */
#[Route('/media/cars')]
final class CarPhotoController extends AbstractController
{
    public function __construct(
        private readonly CarPhotoUploadService $photoUploadService,
    ) {
    }

    #[Route('/{id}.{ext}', name: 'app_car_photo', requirements: ['id' => '\d+', 'ext' => 'jpe?g|png|webp'], methods: ['GET'])]
    public function serve(int $id, string $ext): Response
    {
        $path = $this->photoUploadService->resolveFilesystemPath($id, $ext);
        if ($path === null) {
            throw new NotFoundHttpException('Photo not found.');
        }

        $response = new BinaryFileResponse($path);
        $response->setPublic();
        $response->setMaxAge(86400);
        $response->setSharedMaxAge(86400);

        return $response;
    }
}
