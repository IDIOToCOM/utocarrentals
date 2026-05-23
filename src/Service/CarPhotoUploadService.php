<?php

namespace App\Service;

use App\Entity\CarInventory;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Stores car photos in storage/car_photos (persistent on Forge) and serves them at /uploads/cars/.
 */
final class CarPhotoUploadService
{
    private const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];

    private const TYPE_DEFAULTS = [
        'suv' => 'suv.svg',
        'sedan' => 'sedan.svg',
        'sports car' => 'sports.svg',
        'hatchback' => 'hatchback.svg',
        'truck' => 'truck.svg',
    ];

    public function __construct(
        #[Autowire('%kernel.project_dir%/public')]
        private readonly string $publicDir,
        #[Autowire('%env(default:car_photos_storage_dir:CAR_PHOTOS_STORAGE_DIR)%')]
        private readonly string $carsStorageDir,
    ) {
    }

    private function legacyStorageDir(): string
    {
        return $this->publicDir.'/images/cars';
    }

    public function upload(CarInventory $car, UploadedFile $file): void
    {
        $id = $car->getId();
        if ($id === null) {
            throw new \InvalidArgumentException('Car must be persisted before uploading a photo.');
        }

        if (!$file->isValid()) {
            throw new \RuntimeException($file->getErrorMessage() ?: 'Invalid upload.');
        }

        $error = $this->getValidationError($file);
        if ($error !== null) {
            throw new \InvalidArgumentException($error);
        }

        $ext = $this->resolveAllowedExtension($file);

        $this->removeUploadedPhotos($id);

        $targetDir = $this->carsStorageDir;
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        $this->storeUploadedFile($file, $targetDir.'/'.$id.'.'.$ext);
    }

    /**
     * Validates without MIME guessing (works when php_fileinfo is disabled).
     */
    public function getValidationError(UploadedFile $file, int $maxBytes = 5 * 1024 * 1024): ?string
    {
        if (!$file->isValid()) {
            return $file->getErrorMessage() ?: 'Invalid upload.';
        }

        $size = $file->getSize();
        if ($size !== false && $size > $maxBytes) {
            return 'Photo must be 5 MB or smaller.';
        }

        try {
            $this->assertAllowedImage($file);
        } catch (\InvalidArgumentException $e) {
            return $e->getMessage();
        }

        return null;
    }

    public function hasUploadedPhoto(CarInventory $car): bool
    {
        return $this->getUploadedRelativePath($car) !== null;
    }

    public function removePhoto(CarInventory $car): bool
    {
        $id = $car->getId();
        if ($id === null) {
            return false;
        }

        if (!$this->hasUploadedPhoto($car)) {
            return false;
        }

        $this->removeUploadedPhotos($id);

        return true;
    }

    public function getUploadedRelativePath(CarInventory $car): ?string
    {
        $id = $car->getId();
        if ($id === null) {
            return null;
        }

        foreach (self::ALLOWED_EXTENSIONS as $ext) {
            if (is_file($this->carsStorageDir.'/'.$id.'.'.$ext)) {
                return '/uploads/cars/'.$id.'.'.$ext;
            }
            if (is_file($this->legacyStorageDir().'/'.$id.'.'.$ext)) {
                return '/images/cars/'.$id.'.'.$ext;
            }
        }

        return null;
    }

    public function resolvePublicUrl(CarInventory $car): string
    {
        $uploaded = $this->getUploadedRelativePath($car);
        if ($uploaded !== null) {
            return $uploaded;
        }

        $type = strtolower(trim((string) $car->getType()));
        $defaultFile = self::TYPE_DEFAULTS[$type] ?? 'default.svg';
        $relative = 'images/cars/defaults/'.$defaultFile;

        if (is_file($this->publicDir.'/'.$relative)) {
            return '/'.$relative;
        }

        return '/images/cars/defaults/default.svg';
    }

    private function removeUploadedPhotos(int $carId): void
    {
        foreach (self::ALLOWED_EXTENSIONS as $ext) {
            foreach ([$this->carsStorageDir, $this->legacyStorageDir()] as $dir) {
                $path = $dir.'/'.$carId.'.'.$ext;
                if (is_file($path)) {
                    unlink($path);
                }
            }
        }
    }

    /**
     * Resolve extension from the filename and verify with getimagesize() (no fileinfo required).
     */
    private function resolveAllowedExtension(UploadedFile $file): string
    {
        $this->assertAllowedImage($file);

        return strtolower(pathinfo($file->getClientOriginalName(), \PATHINFO_EXTENSION));
    }

    private function assertAllowedImage(UploadedFile $file): void
    {
        $ext = strtolower(pathinfo($file->getClientOriginalName(), \PATHINFO_EXTENSION));
        if ($ext === '' || !\in_array($ext, self::ALLOWED_EXTENSIONS, true)) {
            throw new \InvalidArgumentException('Photo must be JPG, PNG, or WebP.');
        }

        $path = $file->getPathname();
        if ($path === '' || !is_readable($path)) {
            throw new \RuntimeException('Could not read the uploaded file.');
        }

        $info = @getimagesize($path);
        if ($info === false) {
            throw new \InvalidArgumentException('The uploaded file is not a valid image.');
        }

        $allowedTypes = [\IMAGETYPE_JPEG, \IMAGETYPE_PNG, \IMAGETYPE_WEBP];
        if (!\in_array($info[2], $allowedTypes, true)) {
            throw new \InvalidArgumentException('Photo must be JPG, PNG, or WebP.');
        }
    }

    private function storeUploadedFile(UploadedFile $file, string $targetPath): void
    {
        $source = $file->getPathname();
        if (@rename($source, $targetPath)) {
            @chmod($targetPath, 0666 & ~umask());

            return;
        }

        if (!@copy($source, $targetPath)) {
            throw new \RuntimeException('Could not save the uploaded photo.');
        }

        @chmod($targetPath, 0666 & ~umask());
        @unlink($source);
    }
}
