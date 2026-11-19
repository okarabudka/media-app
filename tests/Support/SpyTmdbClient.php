<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\DTO\MovieDTO;
use App\DTO\MovieListDTO;
use App\Tmdb\TmdbClientInterface;
use App\Tmdb\TmdbException;

/**
 * Records calls instead of talking to TMDB.
 */
final class SpyTmdbClient implements TmdbClientInterface
{
    /** @var list<string> */
    public array $calls = [];

    public bool $failNext = false;

    public function popular(int $page): MovieListDTO
    {
        $this->record('popular:'.$page);

        return new MovieListDTO($page, 1, 0, []);
    }

    public function search(string $query, int $page): MovieListDTO
    {
        $this->record('search:'.$query.':'.$page);

        return new MovieListDTO($page, 1, 0, []);
    }

    public function movie(int $id): MovieDTO
    {
        $this->record('movie:'.$id);

        return new MovieDTO($id, 'Title', '', null, null, 0.0, 0);
    }

    private function record(string $call): void
    {
        $this->calls[] = $call;
        if ($this->failNext) {
            $this->failNext = false;

            throw TmdbException::notConfigured();
        }
    }
}
