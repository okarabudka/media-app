<?php

declare(strict_types=1);

namespace App\Tmdb;

use App\DTO\MovieDTO;
use App\DTO\MovieListDTO;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Caches successful TMDB answers, so repeated searches and page switches do not hit the TMDB rate limit.
 * Failures are not cached: the exception escapes the cache callback before anything is stored.
 */
#[AsDecorator(TmdbClientInterface::class)]
final readonly class CachedTmdbClient implements TmdbClientInterface
{
    public function __construct(
        #[AutowireDecorated]
        private TmdbClientInterface $inner,
        #[Autowire(service: 'cache.tmdb')]
        private CacheInterface $cache,
        #[Autowire(env: 'int:TMDB_CACHE_TTL')]
        private int $ttl,
    ) {
    }

    public function popular(int $page): MovieListDTO
    {
        return $this->remember('popular.'.$page, fn (): MovieListDTO => $this->inner->popular($page));
    }

    public function search(string $query, int $page): MovieListDTO
    {
        $normalized = mb_strtolower(trim($query));

        // Hashed because cache keys must not contain characters such as {}()/\@:
        return $this->remember(
            'search.'.hash('xxh128', $normalized).'.'.$page,
            fn (): MovieListDTO => $this->inner->search($normalized, $page),
        );
    }

    public function movie(int $id): MovieDTO
    {
        return $this->remember('movie.'.$id, fn (): MovieDTO => $this->inner->movie($id));
    }

    /**
     * @template T of MovieDTO|MovieListDTO
     *
     * @param callable(): T $load
     *
     * @return T
     */
    private function remember(string $key, callable $load): MovieDTO|MovieListDTO
    {
        return $this->cache->get($key, function (ItemInterface $item) use ($load): MovieDTO|MovieListDTO {
            $item->expiresAfter($this->ttl);

            return $load();
        });
    }
}
