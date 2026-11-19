<?php

declare(strict_types=1);

namespace App\Tmdb;

use App\DTO\MovieDTO;
use App\DTO\MovieListDTO;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Talks to the TMDB v3 API. The token is added per request and only ever leaves the server towards TMDB.
 */
#[AsAlias(TmdbClientInterface::class)]
final readonly class HttpTmdbClient implements TmdbClientInterface
{
    private const string LANGUAGE = 'en-US';

    public function __construct(
        private HttpClientInterface $tmdbClient,
        private TmdbResponseMapper $mapper,
        #[Autowire(env: 'TMDB_API_KEY')]
        #[\SensitiveParameter]
        private string $apiToken,
    ) {
    }

    public function popular(int $page): MovieListDTO
    {
        return $this->mapper->movieList($this->get('movie/popular', ['page' => $page]));
    }

    public function search(string $query, int $page): MovieListDTO
    {
        return $this->mapper->movieList($this->get('search/movie', [
            'query' => $query,
            'page' => $page,
            'include_adult' => 'false',
        ]));
    }

    public function movie(int $id): MovieDTO
    {
        return $this->mapper->movie($this->get('movie/'.$id, []));
    }

    /**
     * @param array<string, int|string> $query
     *
     * @return array<mixed>
     */
    private function get(string $path, array $query): array
    {
        if ('' === $this->apiToken) {
            throw TmdbException::notConfigured();
        }

        try {
            $response = $this->tmdbClient->request('GET', $path, [
                'query' => $query + ['language' => self::LANGUAGE],
                'auth_bearer' => $this->apiToken,
            ]);

            $status = $response->getStatusCode();
            if (200 !== $status) {
                throw TmdbException::fromStatus($status, $path);
            }

            return $response->toArray();
        } catch (TransportExceptionInterface $e) {
            throw TmdbException::unavailable($path, $e, $this->apiToken);
        } catch (DecodingExceptionInterface) {
            throw TmdbException::invalidResponse(\sprintf('"%s" did not return a JSON object.', $path));
        }
    }
}
