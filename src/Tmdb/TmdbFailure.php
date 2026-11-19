<?php

declare(strict_types=1);

namespace App\Tmdb;

use Symfony\Component\HttpFoundation\Response;

/**
 * Why a TMDB call failed, and what the API answers in that case.
 */
enum TmdbFailure
{
    case NotConfigured;
    case Unauthorized;
    case NotFound;
    case RateLimited;
    case Unavailable;
    case InvalidResponse;

    public function statusCode(): int
    {
        return match ($this) {
            self::NotConfigured, self::RateLimited => Response::HTTP_SERVICE_UNAVAILABLE,
            self::NotFound => Response::HTTP_NOT_FOUND,
            self::Unauthorized, self::Unavailable, self::InvalidResponse => Response::HTTP_BAD_GATEWAY,
        };
    }

    public function publicMessage(): string
    {
        return match ($this) {
            self::NotConfigured => 'The movie database is not configured: set TMDB_API_KEY on the server.',
            self::Unauthorized => 'The movie database rejected the server credentials.',
            self::NotFound => 'Movie not found.',
            self::RateLimited => 'The movie database is busy. Please try again in a few seconds.',
            self::Unavailable => 'The movie database is not reachable right now.',
            self::InvalidResponse => 'The movie database sent an unexpected response.',
        };
    }
}
