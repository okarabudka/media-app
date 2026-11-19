<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Tests\Support\TmdbMock;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

abstract class ApiTestCase extends WebTestCase
{
    protected const string USERNAME = 'demo';
    protected const string PASSWORD = 'test-password';

    protected KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        // One kernel per test, so the rate limiter, the cache and the TMDB mock keep their state between requests.
        $this->client->disableReboot();

        foreach (['cache.tmdb', 'cache.rate_limiter'] as $pool) {
            $cache = static::getContainer()->get($pool);
            self::assertInstanceOf(CacheItemPoolInterface::class, $cache);
            $cache->clear();
        }
    }

    protected function tmdb(): TmdbMock
    {
        return static::getContainer()->get(TmdbMock::class);
    }

    protected function login(string $username = self::USERNAME, string $password = self::PASSWORD): void
    {
        $this->client->request(
            'POST',
            '/api/auth/login',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['username' => $username, 'password' => $password], \JSON_THROW_ON_ERROR),
        );
    }

    /**
     * @return array<mixed>
     */
    protected function json(): array
    {
        $data = json_decode((string) $this->client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($data);

        return $data;
    }

    protected function assertProblem(int $status, string $detail): void
    {
        self::assertResponseStatusCodeSame($status);
        self::assertResponseHeaderSame('Content-Type', 'application/problem+json');
        self::assertSame($detail, $this->json()['detail'] ?? null);
    }
}
