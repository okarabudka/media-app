<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpClient\Response\MockResponse;

final class MovieControllerTest extends ApiTestCase
{
    public function testMoviesRequireLogin(): void
    {
        $this->client->request('GET', '/api/movies');

        $this->assertProblem(401, 'Authentication required.');
        self::assertSame([], $this->tmdb()->requestedUrls());
    }

    public function testPopularMovies(): void
    {
        $this->login();
        $this->client->request('GET', '/api/movies?page=2');

        self::assertResponseIsSuccessful();
        $body = $this->json();
        self::assertSame(1, $body['page']);
        self::assertSame(500, $body['totalPages']);
        self::assertIsArray($body['results']);
        self::assertSame([
            'id' => 872585,
            'title' => 'Oppenheimer',
            'overview' => "The story of J. Robert Oppenheimer's role in the development of the atomic bomb during World War II.",
            'posterUrl' => 'https://image.tmdb.org/t/p/w342/8Gxv8gSFCU0XGDykEGv7zR1n2ua.jpg',
            'releaseYear' => 2023,
            'rating' => 8.1,
            'voteCount' => 9120,
        ], $body['results'][0]);
        self::assertStringContainsString('page=2', $this->tmdb()->requestedUrls()[0]);
    }

    public function testSearch(): void
    {
        $this->login();
        $this->client->request('GET', '/api/movies/search?q=%20Matrix%20');

        self::assertResponseIsSuccessful();
        self::assertSame(1, $this->json()['totalResults']);
        self::assertStringContainsString('query=matrix&page=1', $this->tmdb()->requestedUrls()[0]);
    }

    public function testRepeatedSearchIsServedFromTheCache(): void
    {
        $this->login();
        $this->client->request('GET', '/api/movies/search?q=matrix');
        $this->client->request('GET', '/api/movies/search?q=MATRIX');

        self::assertResponseIsSuccessful();
        self::assertCount(1, $this->tmdb()->requestedUrls());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalidQueries(): iterable
    {
        yield 'q missing' => ['/api/movies/search', 'q'];
        yield 'q blank' => ['/api/movies/search?q=%20%20%20', 'q'];
        yield 'q too short' => ['/api/movies/search?q=a', 'q'];
        yield 'q too short after trim' => ['/api/movies/search?q=%20a%20', 'q'];
        yield 'q too long' => ['/api/movies/search?q='.str_repeat('a', 101), 'q'];
        yield 'search page 0' => ['/api/movies/search?q=matrix&page=0', 'page'];
        yield 'search page 501' => ['/api/movies/search?q=matrix&page=501', 'page'];
        yield 'popular page 0' => ['/api/movies?page=0', 'page'];
        yield 'popular page 501' => ['/api/movies?page=501', 'page'];
        yield 'page not a number' => ['/api/movies?page=abc', 'page'];
    }

    #[DataProvider('invalidQueries')]
    public function testInvalidQueriesAreA400(string $url, string $field): void
    {
        $this->login();
        $this->client->request('GET', $url);

        $this->assertProblem(400, 'The request parameters are invalid.');
        $violations = $this->json()['violations'] ?? null;
        self::assertIsArray($violations);
        self::assertContains($field, array_column($violations, 'field'));
        self::assertSame([], $this->tmdb()->requestedUrls(), 'Invalid input never reaches TMDB');
    }

    public function testMovieDetails(): void
    {
        $this->login();
        $this->client->request('GET', '/api/movies/603');

        self::assertResponseIsSuccessful();
        self::assertSame('The Matrix', $this->json()['title']);
    }

    /**
     * @return iterable<string, array{int, int, string}>
     */
    public static function tmdbErrors(): iterable
    {
        yield 'invalid token' => [401, 502, 'The movie database rejected the server credentials.'];
        yield 'unknown movie' => [404, 404, 'Movie not found.'];
        yield 'rate limited' => [429, 503, 'The movie database is busy. Please try again in a few seconds.'];
        yield 'server error' => [500, 502, 'The movie database is not reachable right now.'];
    }

    #[DataProvider('tmdbErrors')]
    public function testTmdbErrorsAreMapped(int $tmdbStatus, int $expectedStatus, string $detail): void
    {
        $this->login();
        $this->tmdb()->queue(new MockResponse('{}', ['http_code' => $tmdbStatus]));
        $this->client->request('GET', '/api/movies/42');

        $this->assertProblem($expectedStatus, $detail);
        self::assertStringNotContainsString('test-tmdb-token', (string) $this->client->getResponse()->getContent());
    }

    public function testNetworkErrorIsA502(): void
    {
        $this->login();
        $this->tmdb()->queue(new MockResponse('', ['error' => 'Could not resolve host']));
        $this->client->request('GET', '/api/movies');

        $this->assertProblem(502, 'The movie database is not reachable right now.');
    }

    public function testFailuresAreNotCached(): void
    {
        $this->login();
        $this->tmdb()->queue(new MockResponse('{}', ['http_code' => 429]));
        $this->client->request('GET', '/api/movies');
        self::assertResponseStatusCodeSame(503);

        $this->client->request('GET', '/api/movies');
        self::assertResponseIsSuccessful();
        self::assertCount(2, $this->tmdb()->requestedUrls());
    }

    public function testUnknownApiRouteIsJson(): void
    {
        $this->login();
        $this->client->request('GET', '/api/does-not-exist');

        self::assertResponseStatusCodeSame(404);
        self::assertResponseHeaderSame('Content-Type', 'application/problem+json');
    }

    public function testSpaIsServedForFrontendRoutes(): void
    {
        $this->client->request('GET', '/login');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('#app');
        self::assertSelectorExists('script[src="/build/assets/app-test.js"]');
    }
}
