<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tmdb;

use App\Tmdb\TmdbException;
use App\Tmdb\TmdbFailure;
use App\Tmdb\TmdbResponseMapper;
use PHPUnit\Framework\TestCase;

final class TmdbResponseMapperTest extends TestCase
{
    public function testMapsAListWithCompleteAndSparseMovies(): void
    {
        $list = new TmdbResponseMapper()->movieList(self::fixture('popular.json'));

        self::assertSame(1, $list->page);
        self::assertSame(500, $list->totalPages, 'TMDB serves at most 500 pages');
        self::assertSame(964251, $list->totalResults);
        self::assertCount(2, $list->results);

        [$complete, $sparse] = $list->results;
        self::assertSame(872585, $complete->id);
        self::assertSame('Oppenheimer', $complete->title);
        self::assertSame('https://image.tmdb.org/t/p/w342/8Gxv8gSFCU0XGDykEGv7zR1n2ua.jpg', $complete->posterUrl);
        self::assertSame(2023, $complete->releaseYear);
        self::assertSame(8.1, $complete->rating);
        self::assertSame(9120, $complete->voteCount);

        self::assertNull($sparse->posterUrl);
        self::assertNull($sparse->releaseYear);
        self::assertSame('', $sparse->overview);
        self::assertSame(0.0, $sparse->rating);
    }

    public function testMapsASingleMovie(): void
    {
        $movie = new TmdbResponseMapper()->movie(self::fixture('movie.json'));

        self::assertSame(603, $movie->id);
        self::assertSame('The Matrix', $movie->title);
        self::assertSame(1999, $movie->releaseYear);
    }

    public function testFallsBackToTheOriginalTitle(): void
    {
        $movie = new TmdbResponseMapper()->movie(['id' => 1, 'original_title' => 'Original']);

        self::assertSame('Original', $movie->title);
    }

    public function testRejectsAListWithoutResults(): void
    {
        $this->expectExceptionObject(TmdbException::invalidResponse('"results" is missing.'));

        new TmdbResponseMapper()->movieList(['page' => 1]);
    }

    public function testRejectsAMovieWithoutId(): void
    {
        try {
            new TmdbResponseMapper()->movieList(['results' => [['title' => 'No id']]]);
            self::fail('Expected a TmdbException');
        } catch (TmdbException $e) {
            self::assertSame(TmdbFailure::InvalidResponse, $e->failure);
        }
    }

    /**
     * @return array<mixed>
     */
    private static function fixture(string $name): array
    {
        $data = json_decode((string) file_get_contents(\dirname(__DIR__, 2).'/Fixtures/tmdb/'.$name), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($data);

        return $data;
    }
}
