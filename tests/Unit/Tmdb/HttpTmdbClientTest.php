<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tmdb;

use App\Tests\Support\TmdbMock;
use App\Tmdb\HttpTmdbClient;
use App\Tmdb\TmdbException;
use App\Tmdb\TmdbFailure;
use App\Tmdb\TmdbResponseMapper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class HttpTmdbClientTest extends TestCase
{
    private const string TOKEN = 'super-secret-token';

    public function testPopularSendsTheTokenAndMapsTheAnswer(): void
    {
        $response = TmdbMock::fixture('popular.json');
        $list = $this->client($response)->popular(2);

        self::assertSame('GET', $response->getRequestMethod());
        self::assertSame('https://api.themoviedb.org/3/movie/popular?page=2&language=en-US', $response->getRequestUrl());
        $headers = $response->getRequestOptions()['headers'] ?? null;
        self::assertIsArray($headers);
        self::assertContains('Authorization: Bearer '.self::TOKEN, $headers);
        self::assertCount(2, $list->results);
    }

    public function testSearchPassesTheQuery(): void
    {
        $response = TmdbMock::fixture('search.json');
        $list = $this->client($response)->search('the matrix', 1);

        self::assertSame('https://api.themoviedb.org/3/search/movie?query=the%20matrix&page=1&include_adult=false&language=en-US', $response->getRequestUrl());
        self::assertSame(603, $list->results[0]->id);
    }

    public function testMovieLoadsOneMovie(): void
    {
        $response = TmdbMock::fixture('movie.json');

        self::assertSame('The Matrix', $this->client($response)->movie(603)->title);
        self::assertSame('https://api.themoviedb.org/3/movie/603?language=en-US', $response->getRequestUrl());
    }

    /**
     * @return iterable<string, array{MockResponse, TmdbFailure}>
     */
    public static function failures(): iterable
    {
        yield '401 invalid token' => [new MockResponse('{}', ['http_code' => 401]), TmdbFailure::Unauthorized];
        yield '404 unknown movie' => [new MockResponse('{}', ['http_code' => 404]), TmdbFailure::NotFound];
        yield '429 rate limited' => [new MockResponse('{}', ['http_code' => 429]), TmdbFailure::RateLimited];
        yield '500 server error' => [new MockResponse('{}', ['http_code' => 500]), TmdbFailure::Unavailable];
        yield 'network error' => [new MockResponse('', ['error' => 'Connection refused for '.self::TOKEN]), TmdbFailure::Unavailable];
        yield 'not JSON' => [new MockResponse('<html>', ['http_code' => 200]), TmdbFailure::InvalidResponse];
    }

    #[DataProvider('failures')]
    public function testFailuresBecomeTmdbExceptionsWithoutTheToken(MockResponse $response, TmdbFailure $expected): void
    {
        try {
            $this->client($response)->movie(1);
            self::fail('Expected a TmdbException');
        } catch (TmdbException $e) {
            self::assertSame($expected, $e->failure);
            self::assertStringNotContainsString(self::TOKEN, $e->getMessage());
        }
    }

    public function testAnEmptyTokenFailsBeforeAnyRequest(): void
    {
        $http = new MockHttpClient(static fn () => self::fail('No request expected'));
        $client = new HttpTmdbClient($http, new TmdbResponseMapper(), '');

        try {
            $client->popular(1);
            self::fail('Expected a TmdbException');
        } catch (TmdbException $e) {
            self::assertSame(TmdbFailure::NotConfigured, $e->failure);
        }
        self::assertSame(0, $http->getRequestsCount());
    }

    private function client(MockResponse $response): HttpTmdbClient
    {
        $http = new MockHttpClient($response, 'https://api.themoviedb.org/3/');

        return new HttpTmdbClient($http, new TmdbResponseMapper(), self::TOKEN);
    }
}
