<?php

declare(strict_types=1);

namespace App\Controller;

use App\Auth\AuthCookieFactory;
use App\Auth\AuthProviderInterface;
use App\Auth\AuthUser;
use App\DTO\LoginRequest;
use App\Http\Problem;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/auth', name: 'api_auth_')]
final class AuthController extends AbstractController
{
    public function __construct(
        private readonly AuthProviderInterface $authProvider,
        private readonly JWTTokenManagerInterface $tokenManager,
        private readonly AuthCookieFactory $cookieFactory,
        #[Target('login')]
        private readonly RateLimiterFactoryInterface $loginLimiter,
        #[Target('login_ip')]
        private readonly RateLimiterFactoryInterface $loginIpLimiter,
    ) {
    }

    /**
     * Only JSON bodies are accepted: a cross-site HTML form cannot send them, which blocks login CSRF.
     */
    #[Route('/login', name: 'login', methods: ['POST'])]
    public function login(
        Request $request,
        #[MapRequestPayload(acceptFormat: 'json', validationFailedStatusCode: Response::HTTP_BAD_REQUEST)]
        LoginRequest $credentials,
    ): JsonResponse {
        $clientIp = $request->getClientIp() ?? 'unknown';
        $accountLimiter = $this->loginLimiter->create($clientIp.'|'.mb_strtolower($credentials->username));

        foreach ([$this->loginIpLimiter->create($clientIp), $accountLimiter] as $limiter) {
            $limit = $limiter->consume();
            if (!$limit->isAccepted()) {
                throw new TooManyRequestsHttpException(max(1, $limit->getRetryAfter()->getTimestamp() - time()), 'Too many login attempts. Please wait a minute and try again.');
            }
        }

        $user = $this->authProvider->authenticate($credentials->username, $credentials->password);
        if (null === $user) {
            return Problem::response(Response::HTTP_UNAUTHORIZED, 'Invalid username or password.');
        }

        $accountLimiter->reset();

        $response = $this->userResponse($user);
        $response->headers->setCookie($this->cookieFactory->create($this->tokenManager->create($user), $request));

        return $response;
    }

    #[Route('/me', name: 'me', methods: ['GET'])]
    public function me(#[CurrentUser] AuthUser $user): JsonResponse
    {
        return $this->userResponse($user);
    }

    /**
     * Stateless JWTs cannot be revoked; logging out removes the cookie, so the browser no longer has the token.
     */
    #[Route('/logout', name: 'logout', methods: ['POST'])]
    public function logout(Request $request): Response
    {
        $response = new Response(null, Response::HTTP_NO_CONTENT);
        $response->headers->setCookie($this->cookieFactory->clear($request));

        return $response;
    }

    private function userResponse(AuthUser $user): JsonResponse
    {
        $response = new JsonResponse(['username' => $user->username]);
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }
}
