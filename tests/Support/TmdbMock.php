<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Stands in for TMDB in the test environment (framework.http_client.mock_response_factory).
 * Serves JSON fixtures by path; a test can queue a specific response for the next call.
 */
final class TmdbMock
{
    /** @var list<ResponseInterface> */
    private array $queue = [];

    /** @var list<string> */
    private array $requestedUrls = [];

    /**
     * @param array<mixed> $options
     */
    public function __invoke(string $method, string $url, array $options): ResponseInterface
    {
        $this->requestedUrls[] = $url;

        if ([] !== $this->queue) {
            return array_shift($this->queue);
        }

        $fixture = match (parse_url($url, \PHP_URL_PATH)) {
            '/3/movie/popular' => 'popular.json',
            '/3/search/movie' => 'search.json',
            '/3/movie/603' => 'movie.json',
            default => null,
        };

        return null === $fixture
            ? self::fixture('not-found.json', 404)
            : self::fixture($fixture);
    }

    public static function fixture(string $name, int $status = 200): MockResponse
    {
        $body = file_get_contents(\dirname(__DIR__).'/Fixtures/tmdb/'.$name);
        if (false === $body) {
            throw new \RuntimeException('Missing fixture '.$name);
        }

        return new MockResponse($body, ['http_code' => $status, 'response_headers' => ['Content-Type' => 'application/json']]);
    }

    public function queue(ResponseInterface $response): void
    {
        $this->queue[] = $response;
    }

    /**
     * @return list<string>
     */
    public function requestedUrls(): array
    {
        return $this->requestedUrls;
    }
}
