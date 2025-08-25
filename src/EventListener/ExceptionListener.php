<?php

namespace App\EventListener;

use App\Exception\ChecklistNotFoundException;
use App\Exception\EmailDeliveryException;
use App\Exception\InvalidEmployeeIdException;
use App\Exception\InvalidParametersException;
use App\Exception\JsonValidationException;
use App\Exception\SubmissionAlreadyExistsException;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;

class ExceptionListener
{
    public function __construct(
        private LoggerInterface $logger
    ) {}

    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        $request = $event->getRequest();

        // Log the exception
        $this->logger->error('Exception occurred', [
            'exception' => $exception::class,
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'uri' => $request->getUri(),
        ]);

        // Handle API requests differently
        if ($this->isApiRequest($request)) {
            $response = $this->createApiErrorResponse($exception);
            $event->setResponse($response);
            return;
        }

        // Handle web requests
        $response = $this->createWebErrorResponse($exception, $request);
        if ($response) {
            $event->setResponse($response);
        }
    }

    private function isApiRequest($request): bool
    {
        return str_starts_with($request->getPathInfo(), '/api/') 
            || $request->getContentType() === 'json'
            || str_contains($request->headers->get('Accept', ''), 'application/json');
    }

    private function createApiErrorResponse(\Throwable $exception): JsonResponse
    {
        $statusCode = 500;
        $errorCode = 'INTERNAL_ERROR';
        $message = 'An internal error occurred';

        match ($exception::class) {
            JsonValidationException::class, 
            InvalidParametersException::class,
            InvalidEmployeeIdException::class => [
                $statusCode = 400,
                $errorCode = 'VALIDATION_ERROR',
                $message = $exception->getMessage()
            ],
            ChecklistNotFoundException::class => [
                $statusCode = 404,
                $errorCode = 'NOT_FOUND',
                $message = $exception->getMessage()
            ],
            SubmissionAlreadyExistsException::class => [
                $statusCode = 409,
                $errorCode = 'CONFLICT',
                $message = $exception->getMessage()
            ],
            EmailDeliveryException::class => [
                $statusCode = 502,
                $errorCode = 'EMAIL_DELIVERY_FAILED',
                $message = 'Failed to send email'
            ],
            ValidationFailedException::class => [
                $statusCode = 400,
                $errorCode = 'VALIDATION_ERROR',
                $message = $this->formatValidationErrors($exception)
            ],
            default => null
        };

        // Handle HttpExceptionInterface
        if ($exception instanceof HttpExceptionInterface) {
            $statusCode = $exception->getStatusCode();
            $message = $exception->getMessage();
        }

        return new JsonResponse([
            'error' => [
                'code' => $errorCode,
                'message' => $message,
                'timestamp' => date('c'),
            ]
        ], $statusCode);
    }

    private function createWebErrorResponse(\Throwable $exception, $request): ?Response
    {
        // For web requests, let Symfony handle most exceptions naturally
        // Only intercept specific business logic exceptions
        
        if ($exception instanceof SubmissionAlreadyExistsException) {
            // Redirect back with flash message
            if ($request->hasSession()) {
                $request->getSession()->getFlashBag()->add(
                    'warning', 
                    'This checklist has already been submitted for this employee.'
                );
            }
        }

        if ($exception instanceof EmailDeliveryException) {
            if ($request->hasSession()) {
                $request->getSession()->getFlashBag()->add(
                    'error', 
                    'There was a problem sending the email. Please try again later.'
                );
            }
        }

        return null; // Let Symfony handle the response
    }

    private function formatValidationErrors(ValidationFailedException $exception): string
    {
        $errors = [];
        foreach ($exception->getViolations() as $violation) {
            $errors[] = $violation->getPropertyPath() . ': ' . $violation->getMessage();
        }

        return implode('; ', $errors);
    }
}