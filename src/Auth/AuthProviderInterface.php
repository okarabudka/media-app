<?php

declare(strict_types=1);

namespace App\Auth;

/**
 * Source of truth for user accounts. Replace the stub with a database or OAuth implementation
 * by binding another class to this interface; controllers and security config stay unchanged.
 */
interface AuthProviderInterface
{
    /**
     * Returns the user when the credentials are valid, otherwise null.
     */
    public function authenticate(string $username, #[\SensitiveParameter] string $password): ?AuthUser;

    /**
     * Returns the user with this identifier if the account still exists (used when a JWT is presented).
     */
    public function findUser(string $username): ?AuthUser;
}
