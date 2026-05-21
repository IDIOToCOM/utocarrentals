<?php

declare(strict_types=1);

namespace App\MobileApi;

use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Standard JSON shape for mobile clients:
 * { "success", "data", "error", "meta" }.
 */
final class MobileApiEnvelope
{
    public const VERSION = '1.0';

    public static function ok(mixed $data = null, array $meta = []): JsonResponse
    {
        return new JsonResponse(self::build(true, $data, null, $meta));
    }

    /**
     * @param array<string, mixed>|null $details Optional machine-readable details (e.g. validation)
     */
    public static function fail(
        string $code,
        string $message,
        int $httpStatus = 400,
        mixed $data = null,
        ?array $details = null,
    ): JsonResponse {
        $error = ['code' => $code, 'message' => $message];
        if ($details !== null) {
            $error['details'] = $details;
        }

        return new JsonResponse(self::build(false, $data, $error, []), $httpStatus);
    }

    /**
     * @return array{success: bool, data: mixed, error: array<string, mixed>|null, meta: array<string, mixed>}
     */
    private static function build(bool $success, mixed $data, ?array $error, array $meta): array
    {
        return [
            'success' => $success,
            'data' => $data,
            'error' => $error,
            'meta' => array_merge([
                'apiVersion' => self::VERSION,
                'timestamp' => (new \DateTimeImmutable('now'))->format(\DateTimeInterface::ATOM),
            ], $meta),
        ];
    }
}
