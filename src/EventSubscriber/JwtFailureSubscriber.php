<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Auth\AuthCookieFactory;
use App\Http\Problem;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTExpiredEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTFailureEventInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTInvalidEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTNotFoundEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

/**
 * Answers missing, invalid and expired tokens in the same problem format as all other API errors,
 * and removes a cookie whose token is no longer usable.
 */
final readonly class JwtFailureSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private AuthCookieFactory $cookieFactory,
        private RequestStack $requestStack,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            Events::JWT_NOT_FOUND => 'onNotFound',
            Events::JWT_INVALID => 'onInvalid',
            Events::JWT_EXPIRED => 'onExpired',
        ];
    }

    public function onNotFound(JWTNotFoundEvent $event): void
    {
        $this->respond($event, 'Authentication required.', false);
    }

    public function onInvalid(JWTInvalidEvent $event): void
    {
        $this->respond($event, 'Your session is invalid. Please log in again.', true);
    }

    public function onExpired(JWTExpiredEvent $event): void
    {
        $this->respond($event, 'Your session has expired. Please log in again.', true);
    }

    private function respond(JWTFailureEventInterface $event, string $detail, bool $clearCookie): void
    {
        $response = Problem::response(Response::HTTP_UNAUTHORIZED, $detail);
        $request = $this->requestStack->getCurrentRequest();
        if ($clearCookie && null !== $request) {
            $response->headers->setCookie($this->cookieFactory->clear($request));
        }

        $event->setResponse($response);
    }
}
