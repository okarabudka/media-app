<?php

declare(strict_types=1);

namespace App\Tmdb;

/**
 * Messages never contain the API token or the upstream response body.
 */
final class TmdbException extends \RuntimeException
{
    public function __construct(
        public readonly TmdbFailure $failure,
        string $message,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public static function notConfigured(): self
    {
        return new self(TmdbFailure::NotConfigured, 'TMDB_API_KEY is empty.');
    }

    public static function fromStatus(int $status, string $path): self
    {
        $failure = match (true) {
            401 === $status => TmdbFailure::Unauthorized,
            404 === $status => TmdbFailure::NotFound,
            429 === $status => TmdbFailure::RateLimited,
            default => TmdbFailure::Unavailable,
        };

        return new self($failure, \sprintf('TMDB answered %d for "%s".', $status, $path));
    }

    /**
     * The transport error is kept as text only, with the token masked, so it cannot leak through the exception chain.
     */
    public static function unavailable(string $path, \Throwable $cause, #[\SensitiveParameter] string $token): self
    {
        $reason = '' === $token ? $cause->getMessage() : str_replace($token, '***', $cause->getMessage());

        return new self(TmdbFailure::Unavailable, \sprintf('TMDB request "%s" failed: %s', $path, $reason));
    }

    public static function invalidResponse(string $reason): self
    {
        return new self(TmdbFailure::InvalidResponse, 'Unexpected TMDB response: '.$reason);
    }
}
