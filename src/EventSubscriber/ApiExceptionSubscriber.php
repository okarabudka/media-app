<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Http\Problem;
use App\Tmdb\TmdbException;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;

/**
 * Every error under /api becomes a JSON problem response; stack traces never reach the client.
 */
final readonly class ApiExceptionSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private LoggerInterface $logger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::EXCEPTION => 'onException'];
    }

    public function onException(ExceptionEvent $event): void
    {
        if (!str_starts_with($event->getRequest()->getPathInfo(), '/api')) {
            return;
        }

        $event->setResponse($this->toResponse($event->getThrowable()));
    }

    private function toResponse(\Throwable $exception): Response
    {
        if ($exception instanceof TmdbException) {
            $this->logger->warning('TMDB request failed: {message}', ['message' => $exception->getMessage(), 'exception' => $exception]);

            return Problem::response($exception->failure->statusCode(), $exception->failure->publicMessage());
        }

        if ($exception instanceof HttpExceptionInterface) {
            $status = $exception->getStatusCode();
            /** @var array<string, string> $headers */
            $headers = $exception->getHeaders();
            $previous = $exception->getPrevious();

            if ($previous instanceof ValidationFailedException) {
                return Problem::response($status, 'The request parameters are invalid.', $this->violations($previous), $headers);
            }

            $detail = $status < 500 && '' !== $exception->getMessage()
                ? $exception->getMessage()
                : (Response::$statusTexts[$status] ?? 'Error');

            return Problem::response($status, $detail, [], $headers);
        }

        $this->logger->error('Unhandled API exception: {message}', ['message' => $exception->getMessage(), 'exception' => $exception]);

        return Problem::response(Response::HTTP_INTERNAL_SERVER_ERROR, 'An unexpected error occurred.');
    }

    /**
     * @return list<array{field: string, message: string}>
     */
    private function violations(ValidationFailedException $exception): array
    {
        return array_values(array_map(
            static fn (ConstraintViolationInterface $violation): array => [
                'field' => $violation->getPropertyPath(),
                'message' => (string) $violation->getMessage(),
            ],
            iterator_to_array($exception->getViolations()),
        ));
    }
}
