<?php

declare(strict_types=1);

namespace App\Tests\Functional;

final class AuthControllerTest extends ApiTestCase
{
    public function testLoginSetsAnHttpOnlyStrictCookieAndNoTokenInTheBody(): void
    {
        $this->login();

        self::assertResponseIsSuccessful();
        self::assertSame(['username' => 'demo'], $this->json());

        $cookie = $this->client->getResponse()->headers->getCookies()[0] ?? null;
        self::assertNotNull($cookie);
        self::assertSame('auth_token', $cookie->getName());
        self::assertNotEmpty($cookie->getValue());
        self::assertTrue($cookie->isHttpOnly());
        self::assertSame('strict', $cookie->getSameSite());
        self::assertSame('/', $cookie->getPath());
        self::assertFalse($cookie->isSecure(), 'Plain HTTP in tests; HTTPS requests get the Secure flag');
    }

    public function testCookieIsSecureOnHttps(): void
    {
        $this->client->request('POST', '/api/auth/login', server: ['CONTENT_TYPE' => 'application/json', 'HTTPS' => 'on'], content: '{"username":"demo","password":"test-password"}');

        self::assertResponseIsSuccessful();
        self::assertTrue($this->client->getResponse()->headers->getCookies()[0]->isSecure());
    }

    public function testWrongPasswordIsRejected(): void
    {
        $this->login(password: 'wrong');

        $this->assertProblem(401, 'Invalid username or password.');
        self::assertSame([], $this->client->getResponse()->headers->getCookies());
    }

    public function testMissingFieldsAreA400(): void
    {
        $this->client->request('POST', '/api/auth/login', server: ['CONTENT_TYPE' => 'application/json'], content: '{"username":""}');

        $this->assertProblem(400, 'The request parameters are invalid.');
        self::assertSame(['username', 'password'], array_column($this->violations(), 'field'));
    }

    public function testFormPostsAreRefused(): void
    {
        // A cross-site HTML form can only send form encodings; refusing them blocks login CSRF.
        $this->client->request('POST', '/api/auth/login', ['username' => 'demo', 'password' => 'test-password']);

        self::assertResponseStatusCodeSame(415);
    }

    public function testTheSixthAttemptWithinAMinuteIsRateLimited(): void
    {
        for ($i = 0; $i < 5; ++$i) {
            $this->login(password: 'wrong');
            self::assertResponseStatusCodeSame(401);
        }

        $this->login();

        $this->assertProblem(429, 'Too many login attempts. Please wait a minute and try again.');
        self::assertResponseHasHeader('Retry-After');
    }

    public function testUsernameRotationFromOneIpIsRateLimited(): void
    {
        for ($i = 0; $i < 20; ++$i) {
            $this->login('user'.$i, 'wrong');
            self::assertResponseStatusCodeSame(401);
        }

        $this->login('another-user', 'wrong');

        self::assertResponseStatusCodeSame(429);
    }

    public function testMeRequiresTheCookie(): void
    {
        $this->client->request('GET', '/api/auth/me');

        $this->assertProblem(401, 'Authentication required.');
    }

    public function testMeReturnsTheLoggedInUser(): void
    {
        $this->login();
        $this->client->request('GET', '/api/auth/me');

        self::assertResponseIsSuccessful();
        self::assertSame(['username' => 'demo'], $this->json());
    }

    public function testATamperedCookieIsRejectedAndCleared(): void
    {
        $this->client->getCookieJar()->set(new \Symfony\Component\BrowserKit\Cookie('auth_token', 'not-a-jwt'));
        $this->client->request('GET', '/api/auth/me');

        $this->assertProblem(401, 'Your session is invalid. Please log in again.');
        self::assertTrue($this->client->getResponse()->headers->getCookies()[0]->isCleared());
    }

    public function testAuthorizationHeaderIsNotAccepted(): void
    {
        $this->login();
        $token = $this->client->getCookieJar()->get('auth_token')?->getValue();
        self::assertNotNull($token);
        $this->client->getCookieJar()->clear();

        $this->client->request('GET', '/api/auth/me', server: ['HTTP_AUTHORIZATION' => 'Bearer '.$token]);

        self::assertResponseStatusCodeSame(401);
    }

    public function testLogoutClearsTheCookie(): void
    {
        $this->login();
        $this->client->request('POST', '/api/auth/logout');

        self::assertResponseStatusCodeSame(204);
        self::assertTrue($this->client->getResponse()->headers->getCookies()[0]->isCleared());

        $this->client->request('GET', '/api/auth/me');
        self::assertResponseStatusCodeSame(401);
    }

    /**
     * @return array<mixed>
     */
    private function violations(): array
    {
        $violations = $this->json()['violations'] ?? null;
        self::assertIsArray($violations);

        return $violations;
    }
}
