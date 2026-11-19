<?php

declare(strict_types=1);

namespace App\Auth;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;

/**
 * Builds the cookie that carries the JWT: httpOnly (no JavaScript access) and SameSite=Strict
 * (never sent on cross-site requests, which is the CSRF protection for the API).
 */
final readonly class AuthCookieFactory
{
    public function __construct(
        #[Autowire(param: 'app.auth_cookie_name')]
        private string $name,
        #[Autowire(param: 'lexik_jwt_authentication.token_ttl')]
        private int $ttl,
        #[Autowire(env: 'bool:JWT_COOKIE_SECURE')]
        private bool $forceSecure,
    ) {
    }

    public function create(string $token, Request $request): Cookie
    {
        return $this->build($token, time() + $this->ttl, $request);
    }

    public function clear(Request $request): Cookie
    {
        return $this->build(null, 1, $request);
    }

    private function build(?string $value, int $expires, Request $request): Cookie
    {
        return Cookie::create($this->name)
            ->withValue($value)
            ->withExpires($expires)
            ->withPath('/')
            ->withSecure($this->forceSecure || $request->isSecure())
            ->withHttpOnly(true)
            ->withSameSite(Cookie::SAMESITE_STRICT);
    }
}
