<?php

declare(strict_types=1);

namespace App\Http;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Error responses of the API in the RFC 9457 "problem details" format.
 */
final class Problem
{
    public const string CONTENT_TYPE = 'application/problem+json';

    /**
     * @param list<array{field: string, message: string}> $violations
     * @param array<string, string>                       $headers
     */
    public static function response(int $status, string $detail, array $violations = [], array $headers = []): JsonResponse
    {
        $body = [
            'type' => 'about:blank',
            'title' => Response::$statusTexts[$status] ?? 'Error',
            'status' => $status,
            'detail' => $detail,
        ];
        if ([] !== $violations) {
            $body['violations'] = $violations;
        }

        return new JsonResponse($body, $status, ['Content-Type' => self::CONTENT_TYPE] + $headers);
    }
}
