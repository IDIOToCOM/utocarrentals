<?php

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD)]
class SafeImageFile extends Constraint
{
    public string $maxSizeMessage = 'Photo must be 5 MB or smaller.';

    public string $invalidMessage = 'Please upload a JPG, PNG, or WebP image.';

    public int $maxBytes = 5 * 1024 * 1024;
}
