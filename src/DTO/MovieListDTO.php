<?php

declare(strict_types=1);

namespace App\DTO;

final readonly class MovieListDTO
{
    /** TMDB never serves pages beyond 500, even when total_pages says more. */
    public const int MAX_PAGES = 500;

    /**
     * @param list<MovieDTO> $results
     */
    public function __construct(
        public int $page,
        public int $totalPages,
        public int $totalResults,
        public array $results,
    ) {
    }
}
