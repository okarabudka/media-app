<?php

declare(strict_types=1);

namespace App\Tmdb;

use App\DTO\MovieDTO;
use App\DTO\MovieListDTO;

/**
 * Turns raw TMDB JSON into DTOs. Required fields are checked, optional ones fall back to safe defaults.
 */
final readonly class TmdbResponseMapper
{
    public const string POSTER_BASE_URL = 'https://image.tmdb.org/t/p/w342';

    /**
     * @param array<mixed> $data
     */
    public function movieList(array $data): MovieListDTO
    {
        $results = $data['results'] ?? null;
        if (!\is_array($results)) {
            throw TmdbException::invalidResponse('"results" is missing.');
        }

        $movies = [];
        foreach ($results as $item) {
            if (!\is_array($item)) {
                throw TmdbException::invalidResponse('a result is not an object.');
            }
            $movies[] = $this->movie($item);
        }

        return new MovieListDTO(
            page: $this->int($data, 'page') ?? 1,
            totalPages: min($this->int($data, 'total_pages') ?? 0, MovieListDTO::MAX_PAGES),
            totalResults: $this->int($data, 'total_results') ?? 0,
            results: $movies,
        );
    }

    /**
     * @param array<mixed> $data
     */
    public function movie(array $data): MovieDTO
    {
        $id = $this->int($data, 'id') ?? throw TmdbException::invalidResponse('a movie has no id.');
        $posterPath = $this->string($data, 'poster_path');

        return new MovieDTO(
            id: $id,
            title: $this->string($data, 'title') ?? $this->string($data, 'original_title') ?? '',
            overview: $this->string($data, 'overview') ?? '',
            posterUrl: null !== $posterPath && '' !== $posterPath ? self::POSTER_BASE_URL.$posterPath : null,
            releaseYear: $this->year($this->string($data, 'release_date')),
            rating: round($this->float($data, 'vote_average'), 1),
            voteCount: $this->int($data, 'vote_count') ?? 0,
        );
    }

    private function year(?string $date): ?int
    {
        if (null === $date || 1 !== preg_match('/^(\d{4})-\d{2}-\d{2}$/', $date, $matches)) {
            return null;
        }

        return (int) $matches[1];
    }

    /**
     * @param array<mixed> $data
     */
    private function int(array $data, string $key): ?int
    {
        return \is_int($data[$key] ?? null) ? $data[$key] : null;
    }

    /**
     * @param array<mixed> $data
     */
    private function string(array $data, string $key): ?string
    {
        return \is_string($data[$key] ?? null) ? $data[$key] : null;
    }

    /**
     * @param array<mixed> $data
     */
    private function float(array $data, string $key): float
    {
        $value = $data[$key] ?? null;

        return \is_int($value) || \is_float($value) ? (float) $value : 0.0;
    }
}
