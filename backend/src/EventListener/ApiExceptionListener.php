<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Exception\DuplicateItemNameException;
use App\Exception\InvalidParentTypeException;
use App\Exception\ItemNotFoundException;
use App\Exception\ParentNotFoundException;
use App\Exception\RootChangeForbiddenException;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;

#[AsEventListener]
final class ApiExceptionListener
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(ExceptionEvent $event): void
    {
        $response = $this->responseFor($event->getThrowable());

        if (null !== $response) {
            $event->setResponse($response);
        }
    }

    private function responseFor(\Throwable $throwable): ?JsonResponse
    {
        $validationFailure = $this->validationFailureIn($throwable);

        if (null !== $validationFailure) {
            return $this->envelope(
                Response::HTTP_BAD_REQUEST,
                'validation_failed',
                'The request is invalid.',
                $this->violationDetails($validationFailure),
            );
        }

        if ($throwable instanceof ItemNotFoundException || $throwable instanceof ParentNotFoundException) {
            return $this->envelope(Response::HTTP_NOT_FOUND, 'not_found', $throwable->getMessage());
        }

        if ($throwable instanceof RootChangeForbiddenException) {
            return $this->envelope(
                Response::HTTP_BAD_REQUEST,
                'validation_failed',
                $throwable->getMessage(),
                [['field' => 'id', 'message' => $throwable->getMessage()]],
            );
        }

        if ($throwable instanceof InvalidParentTypeException) {
            return $this->envelope(
                Response::HTTP_BAD_REQUEST,
                'validation_failed',
                $throwable->getMessage(),
                [['field' => 'parentId', 'message' => $throwable->getMessage()]],
            );
        }

        if ($throwable instanceof DuplicateItemNameException) {
            return $this->envelope(
                Response::HTTP_CONFLICT,
                'conflict',
                $throwable->getMessage(),
                [['field' => 'name', 'message' => $throwable->getMessage()]],
            );
        }

        if ($throwable instanceof HttpExceptionInterface) {
            if (Response::HTTP_BAD_REQUEST === $throwable->getStatusCode()) {
                return $this->envelope(
                    Response::HTTP_BAD_REQUEST,
                    'validation_failed',
                    'The request body is invalid.',
                    [],
                );
            }

            // Framework statuses (unknown route 404, 405, 415) keep their
            // defaults; they are not part of the envelope vocabulary.
            return null;
        }

        // Handled here, so the framework's error listener never logs it.
        $this->logger->error('Unhandled exception.', ['exception' => $throwable]);

        return $this->envelope(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            'internal_error',
            'An unexpected error occurred.',
        );
    }

    private function validationFailureIn(\Throwable $throwable): ?ValidationFailedException
    {
        for ($current = $throwable; null !== $current; $current = $current->getPrevious()) {
            if ($current instanceof ValidationFailedException) {
                return $current;
            }
        }

        return null;
    }

    /**
     * @return list<array{field: string, message: string}>
     */
    private function violationDetails(ValidationFailedException $failure): array
    {
        $details = [];

        foreach ($failure->getViolations() as $violation) {
            $details[] = [
                'field' => $violation->getPropertyPath(),
                'message' => strtr((string) $violation->getMessage(), $violation->getParameters()),
            ];
        }

        return $details;
    }

    /**
     * @param list<array{field: string, message: string}>|null $details
     */
    private function envelope(int $status, string $code, string $message, ?array $details = null): JsonResponse
    {
        $error = [
            'code' => $code,
            'message' => $message,
        ];

        if (null !== $details) {
            $error['details'] = $details;
        }

        return new JsonResponse(['error' => $error], $status);
    }
}
