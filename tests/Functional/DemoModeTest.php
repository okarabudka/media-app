<?php

declare(strict_types=1);

namespace App\Tests\Functional;

/**
 * `make demo`: with TMDB_DEMO=1 the API serves the sample data without a token and without calling TMDB.
 */
final class DemoModeTest extends ApiTestCase
{
    /** @var array<string, array{mixed, mixed}> */
    private array $previous = [];

    protected function setUp(): void
    {
        foreach (['TMDB_DEMO' => '1', 'TMDB_API_KEY' => ''] as $name => $value) {
            $this->previous[$name] = [$_ENV[$name] ?? null, $_SERVER[$name] ?? null];
            $_ENV[$name] = $_SERVER[$name] = $value;
        }

        parent::setUp();
    }

    protected function tearDown(): void
    {
        foreach ($this->previous as $name => [$env, $server]) {
            $_ENV[$name] = $env;
            $_SERVER[$name] = $server;
        }

        parent::tearDown();
    }

    public function testMoviesComeFromTheSampleData(): void
    {
        $this->login();
        $this->client->request('GET', '/api/movies');

        self::assertResponseIsSuccessful();
        $body = $this->json();
        self::assertSame(26, $body['totalResults']);
        self::assertIsArray($body['results']);
        self::assertIsArray($body['results'][0]);
        self::assertSame("The Lighthouse Keeper's Ledger", $body['results'][0]['title']);
        self::assertSame([], $this->tmdb()->requestedUrls());
    }

    public function testSearchAndDetails(): void
    {
        $this->login();
        $this->client->request('GET', '/api/movies/search?q=comets');
        self::assertResponseIsSuccessful();
        self::assertSame(1, $this->json()['totalResults']);

        $this->client->request('GET', '/api/movies/900002');
        self::assertResponseIsSuccessful();
        self::assertSame('Paper Comets', $this->json()['title']);

        $this->client->request('GET', '/api/movies/1');
        $this->assertProblem(404, 'Movie not found.');
        self::assertSame([], $this->tmdb()->requestedUrls());
    }
}
