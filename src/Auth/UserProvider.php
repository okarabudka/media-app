<?php

declare(strict_types=1);

namespace App\Auth;

use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * Loads the user named in a JWT, so a token stops working once its account is gone.
 *
 * @implements UserProviderInterface<AuthUser>
 */
final readonly class UserProvider implements UserProviderInterface
{
    public function __construct(
        private AuthProviderInterface $authProvider,
    ) {
    }

    public function loadUserByIdentifier(string $identifier): AuthUser
    {
        $user = $this->authProvider->findUser($identifier);
        if (null === $user) {
            $exception = new UserNotFoundException();
            $exception->setUserIdentifier($identifier);

            throw $exception;
        }

        return $user;
    }

    public function refreshUser(UserInterface $user): AuthUser
    {
        if (!$user instanceof AuthUser) {
            throw new UnsupportedUserException(\sprintf('Unsupported user class "%s".', $user::class));
        }

        return $this->loadUserByIdentifier($user->getUserIdentifier());
    }

    public function supportsClass(string $class): bool
    {
        return AuthUser::class === $class;
    }
}
