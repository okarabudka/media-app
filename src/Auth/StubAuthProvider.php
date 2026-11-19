<?php

declare(strict_types=1);

namespace App\Auth;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * A single account configured through AUTH_USERNAME and AUTH_PASSWORD.
 * Fails closed: while either value is empty, nobody can log in.
 */
final readonly class StubAuthProvider implements AuthProviderInterface
{
    public function __construct(
        #[Autowire(env: 'AUTH_USERNAME')]
        private string $username,
        #[Autowire(env: 'AUTH_PASSWORD')]
        #[\SensitiveParameter]
        private string $password,
    ) {
    }

    public function authenticate(string $username, #[\SensitiveParameter] string $password): ?AuthUser
    {
        if ('' === $this->username || '' === $this->password) {
            return null;
        }

        // Both comparisons always run, so the response time does not reveal which one failed.
        $usernameMatches = hash_equals($this->username, $username);
        $passwordMatches = hash_equals($this->password, $password);

        return $usernameMatches && $passwordMatches ? new AuthUser($this->username) : null;
    }

    public function findUser(string $username): ?AuthUser
    {
        if ('' === $this->username || '' === $this->password || !hash_equals($this->username, $username)) {
            return null;
        }

        return new AuthUser($this->username);
    }
}
