<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tmdb;

use App\Tests\Support\SpyTmdbClient;
use App\Tmdb\DemoTmdbClient;
use App\Tmdb\TmdbException;
use App\Tmdb\TmdbFailure;
use App\Tmdb\TmdbResponseMapper;
use PHPUnit\Framework\TestCase;

final class DemoTmdbClientTest extends TestCase
{
    private const string DATA_FILE = __DIR__.'/../../../fixtures/demo/movies.json';

    public function testDisabledDelegatesEveryCall(): void
    {
        $inner = new SpyTmdbClient();
        $client = $this->client($inner, false);

        $client->popular(2);
        $client->search('comet', 1);
        $client->movie(900001);

        self::assertSame(['popular:2', 'search:comet:1', 'movie:900001'], $inner->calls);
    }

    public function testPopularPagesThroughTheSampleData(): void
    {
        $inner = new SpyTmdbClient();
        $client = $this->client($inner);

        $first = $client->popular(1);
        $second = $client->popular(2);

        self::assertSame(26, $first->totalResults);
        self::assertSame(2, $first->totalPages);
        self::assertCount(DemoTmdbClient::PAGE_SIZE, $first->results);
        self::assertSame("The Lighthouse Keeper's Ledger", $first->results[0]->title);
        self::assertCount(6, $second->results);
        self::assertSame([], $client->popular(3)->results);
        self::assertSame([], $inner->calls);
    }

    public function testSearchMatchesTitlesCaseInsensitively(): void
    {
        $result = $this->client(new SpyTmdbClient())->search(' MAP ', 1);

        self::assertSame(['The Last Map of Verenna', 'The Map Room'], array_map(
            static fn ($movie): string => $movie->title,
            $result->results,
        ));
        self::assertSame(1, $result->totalPages);
    }

    public function testSearchWithoutMatchesIsEmpty(): void
    {
        $result = $this->client(new SpyTmdbClient())->search('zzz', 1);

        self::assertSame(0, $result->totalResults);
        self::assertSame([], $result->results);
    }

    public function testMovieById(): void
    {
        $movie = $this->client(new SpyTmdbClient())->movie(900005);

        self::assertSame('Orbit of Small Things', $movie->title);
        self::assertSame(2023, $movie->releaseYear);
        self::assertNull($movie->posterUrl);
    }

    public function testUnknownMovieIsNotFound(): void
    {
        try {
            $this->client(new SpyTmdbClient())->movie(42);
            self::fail('Expected a TmdbException');
        } catch (TmdbException $e) {
            self::assertSame(TmdbFailure::NotFound, $e->failure);
        }
    }

    public function testMissingDataFileFailsLoudly(): void
    {
        $client = new DemoTmdbClient(new SpyTmdbClient(), new TmdbResponseMapper(), true, __DIR__.'/does-not-exist.json');

        $this->expectException(TmdbException::class);
        $client->popular(1);
    }

    private function client(SpyTmdbClient $inner, bool $enabled = true): DemoTmdbClient
    {
        return new DemoTmdbClient($inner, new TmdbResponseMapper(), $enabled, self::DATA_FILE);
    }
}
