<?php

declare(strict_types=1);

namespace App\Tests\Functional;

/**
 * Without TMDB_API_KEY the API explains the problem instead of failing with a stack trace.
 */
final class MissingTmdbTokenTest extends ApiTestCase
{
    private mixed $previousEnv;
    private mixed $previousServer;

    protected function setUp(): void
    {
        $this->previousEnv = $_ENV['TMDB_API_KEY'] ?? null;
        $this->previousServer = $_SERVER['TMDB_API_KEY'] ?? null;
        $_ENV['TMDB_API_KEY'] = $_SERVER['TMDB_API_KEY'] = '';

        parent::setUp();
    }

    protected function tearDown(): void
    {
        $_ENV['TMDB_API_KEY'] = $this->previousEnv;
        $_SERVER['TMDB_API_KEY'] = $this->previousServer;

        parent::tearDown();
    }

    public function testMoviesAnswer503WithAClearMessage(): void
    {
        $this->login();
        $this->client->request('GET', '/api/movies');

        $this->assertProblem(503, 'The movie database is not configured: set TMDB_API_KEY on the server.');
        self::assertSame([], $this->tmdb()->requestedUrls());
    }
}
