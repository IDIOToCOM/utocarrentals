<?php

namespace App\Validator\Constraints;

use App\Service\CarPhotoUploadService;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * Validates image uploads without Symfony MIME guessing (no fileinfo required).
 */
final class SafeImageFileValidator extends ConstraintValidator
{
    public function __construct(
        private readonly CarPhotoUploadService $carPhotoUploadService,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof SafeImageFile) {
            throw new UnexpectedTypeException($constraint, SafeImageFile::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        if (!$value instanceof UploadedFile) {
            throw new UnexpectedTypeException($value, UploadedFile::class);
        }

        $error = $this->carPhotoUploadService->getValidationError($value, $constraint->maxBytes);
        if ($error !== null) {
            $message = str_contains(strtolower($error), 'mb') || str_contains(strtolower($error), 'large')
                ? $constraint->maxSizeMessage
                : $constraint->invalidMessage;
            $this->context->buildViolation($message)->addViolation();
        }
    }
}
