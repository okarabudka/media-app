<?php

declare(strict_types=1);

namespace App\Tmdb;

use App\DTO\MovieDTO;
use App\DTO\MovieListDTO;

interface TmdbClientInterface
{
    /**
     * @throws TmdbException
     */
    public function popular(int $page): MovieListDTO;

    /**
     * @throws TmdbException
     */
    public function search(string $query, int $page): MovieListDTO;

    /**
     * @throws TmdbException
     */
    public function movie(int $id): MovieDTO;
}
