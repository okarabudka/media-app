<?php

declare(strict_types=1);

namespace App\Tmdb;

use App\DTO\MovieDTO;
use App\DTO\MovieListDTO;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;

/**
 * With TMDB_DEMO=1 (`make demo`) movies come from fictional sample data in the repository instead of TMDB,
 * so the app can be tried without a token. Otherwise every call goes to the real client.
 * The priority puts it below the cache decorator, so the cache keeps working the same way in both modes.
 */
#[AsDecorator(TmdbClientInterface::class, priority: 10)]
final readonly class DemoTmdbClient implements TmdbClientInterface
{
    public const int PAGE_SIZE = 20;

    public function __construct(
        #[AutowireDecorated]
        private TmdbClientInterface $inner,
        private TmdbResponseMapper $mapper,
        #[Autowire(env: 'bool:TMDB_DEMO')]
        private bool $enabled,
        #[Autowire('%kernel.project_dir%/fixtures/demo/movies.json')]
        private string $dataFile,
    ) {
    }

    public function popular(int $page): MovieListDTO
    {
        return $this->enabled ? $this->page($this->movies(), $page) : $this->inner->popular($page);
    }

    public function search(string $query, int $page): MovieListDTO
    {
        if (!$this->enabled) {
            return $this->inner->search($query, $page);
        }

        $needle = trim($query);
        $matches = array_values(array_filter(
            $this->movies(),
            static fn (array $movie): bool => \is_string($movie['title'] ?? null) && false !== mb_stripos($movie['title'], $needle),
        ));

        return $this->page($matches, $page);
    }

    public function movie(int $id): MovieDTO
    {
        if (!$this->enabled) {
            return $this->inner->movie($id);
        }

        foreach ($this->movies() as $movie) {
            if ($id === ($movie['id'] ?? null)) {
                return $this->mapper->movie($movie);
            }
        }

        throw TmdbException::fromStatus(404, 'movie/'.$id);
    }

    /**
     * @param list<array<mixed>> $movies
     */
    private function page(array $movies, int $page): MovieListDTO
    {
        return $this->mapper->movieList([
            'page' => $page,
            'results' => \array_slice($movies, ($page - 1) * self::PAGE_SIZE, self::PAGE_SIZE),
            'total_pages' => (int) ceil(\count($movies) / self::PAGE_SIZE),
            'total_results' => \count($movies),
        ]);
    }

    /**
     * @return list<array<mixed>>
     */
    private function movies(): array
    {
        $json = is_readable($this->dataFile) ? file_get_contents($this->dataFile) : false;
        if (false === $json) {
            throw TmdbException::invalidResponse(\sprintf('demo data "%s" is not readable.', $this->dataFile));
        }

        try {
            $data = json_decode($json, true, flags: \JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw TmdbException::invalidResponse(\sprintf('demo data is not valid JSON: %s', $e->getMessage()));
        }

        $results = \is_array($data) ? ($data['results'] ?? null) : null;
        if (!\is_array($results) || !array_is_list($results)) {
            throw TmdbException::invalidResponse('demo data has no "results" list.');
        }

        $movies = [];
        foreach ($results as $movie) {
            $movies[] = \is_array($movie) ? $movie : throw TmdbException::invalidResponse('a demo movie is not an object.');
        }

        return $movies;
    }
}
