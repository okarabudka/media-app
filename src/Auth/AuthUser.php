<?php

declare(strict_types=1);

namespace App\Auth;

use Symfony\Component\Security\Core\User\UserInterface;

/**
 * The authenticated user. It holds no password: credentials are checked by the auth provider only.
 */
final readonly class AuthUser implements UserInterface
{
    /**
     * @param non-empty-string $username
     */
    public function __construct(
        public string $username,
    ) {
    }

    public function getRoles(): array
    {
        return ['ROLE_USER'];
    }

    public function getUserIdentifier(): string
    {
        return $this->username;
    }
}
