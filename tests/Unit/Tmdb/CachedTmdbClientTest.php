<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tmdb;

use App\Tests\Support\SpyTmdbClient;
use App\Tmdb\CachedTmdbClient;
use App\Tmdb\TmdbException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

final class CachedTmdbClientTest extends TestCase
{
    public function testRepeatedCallsHitTheInnerClientOnce(): void
    {
        $inner = new SpyTmdbClient();
        $client = new CachedTmdbClient($inner, new ArrayAdapter(), 600);

        $client->popular(1);
        $client->popular(1);
        $client->popular(2);
        $client->movie(603);
        $client->movie(603);

        self::assertSame(['popular:1', 'popular:2', 'movie:603'], $inner->calls);
    }

    public function testSearchTermsAreNormalisedForTheCacheKey(): void
    {
        $inner = new SpyTmdbClient();
        $client = new CachedTmdbClient($inner, new ArrayAdapter(), 600);

        $client->search('Matrix', 1);
        $client->search('  matrix ', 1);
        $client->search('matrix', 2);

        self::assertSame(['search:matrix:1', 'search:matrix:2'], $inner->calls);
    }

    public function testFailuresAreNotCached(): void
    {
        $inner = new SpyTmdbClient();
        $inner->failNext = true;
        $client = new CachedTmdbClient($inner, new ArrayAdapter(), 600);

        try {
            $client->popular(1);
            self::fail('Expected a TmdbException');
        } catch (TmdbException) {
        }
        $client->popular(1);

        self::assertSame(['popular:1', 'popular:1'], $inner->calls);
    }
}
