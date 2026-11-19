<?php

declare(strict_types=1);

namespace App\Tests\Unit\Auth;

use App\Auth\StubAuthProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class StubAuthProviderTest extends TestCase
{
    public function testValidCredentialsReturnTheUser(): void
    {
        $user = new StubAuthProvider('demo', 'secret')->authenticate('demo', 'secret');

        self::assertNotNull($user);
        self::assertSame('demo', $user->getUserIdentifier());
        self::assertSame(['ROLE_USER'], $user->getRoles());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalidCredentials(): iterable
    {
        yield 'wrong password' => ['demo', 'wrong'];
        yield 'wrong username' => ['other', 'secret'];
        yield 'username differs in case' => ['Demo', 'secret'];
        yield 'empty input' => ['', ''];
    }

    #[DataProvider('invalidCredentials')]
    public function testInvalidCredentialsAreRejected(string $username, string $password): void
    {
        self::assertNull(new StubAuthProvider('demo', 'secret')->authenticate($username, $password));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function incompleteConfiguration(): iterable
    {
        yield 'no username' => ['', 'secret'];
        yield 'no password' => ['demo', ''];
        yield 'nothing' => ['', ''];
    }

    #[DataProvider('incompleteConfiguration')]
    public function testIncompleteConfigurationRejectsEveryLogin(string $username, string $password): void
    {
        $provider = new StubAuthProvider($username, $password);

        self::assertNull($provider->authenticate($username, $password));
        self::assertNull($provider->findUser($username));
    }

    public function testFindUserOnlyKnowsTheConfiguredAccount(): void
    {
        $provider = new StubAuthProvider('demo', 'secret');

        self::assertSame('demo', $provider->findUser('demo')?->username);
        self::assertNull($provider->findUser('someone-else'));
    }
}
