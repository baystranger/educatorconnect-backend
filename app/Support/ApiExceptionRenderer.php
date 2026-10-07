<?php

namespace App\Support;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Exceptions\UnauthorizedException as RoleUnauthorizedException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Renders every API exception in the shared envelope:
 * { success: false, message: string, errors?: object }
 *
 * Internal details are only exposed when APP_DEBUG is enabled.
 */
class ApiExceptionRenderer
{
    public static function render(Throwable $e): JsonResponse
    {
        [$status, $message, $errors] = match (true) {
            $e instanceof ValidationException => [422, $e->validator->errors()->first() ?: 'The given data was invalid.', $e->errors()],
            $e instanceof AuthenticationException => [401, self::authMessage($e), null],
            $e instanceof AuthorizationException, $e instanceof RoleUnauthorizedException => [403, self::forbiddenMessage($e), null],
            $e instanceof ModelNotFoundException => [404, 'The requested resource was not found.', null],
            $e instanceof TokenMismatchException => [419, 'Your session expired. Please refresh and try again.', null],
            $e instanceof ThrottleRequestsException => [429, 'Too many requests. Please wait a moment and try again.', null],
            $e instanceof HttpExceptionInterface => [$e->getStatusCode(), self::httpMessage($e), null],
            default => [500, self::defaultMessage(500), null],
        };

        if ($status >= 500) {
            report($e);
        }

        $payload = ['success' => false, 'message' => $message];
        if ($errors) {
            $payload['errors'] = $errors;
        }
        if ($status >= 500 && config('app.debug')) {
            $payload['debug'] = ['exception' => $e::class, 'message' => $e->getMessage()];
        }

        $headers = $e instanceof HttpExceptionInterface ? $e->getHeaders() : [];

        return response()->json($payload, $status, $headers);
    }

    private static function authMessage(AuthenticationException $e): string
    {
        return $e->getMessage() === 'Unauthenticated.' || $e->getMessage() === ''
            ? 'Please sign in to continue.'
            : $e->getMessage();
    }

    private static function forbiddenMessage(Throwable $e): string
    {
        $message = $e->getMessage();

        return ($message === '' || $message === 'This action is unauthorized.' || str_starts_with($message, 'User does not have'))
            ? 'You do not have permission to do that.'
            : $message;
    }

    private static function httpMessage(HttpExceptionInterface $e): string
    {
        $message = $e->getMessage();
        // Route-not-found messages from Symfony leak internal paths.
        if ($message === '' || str_starts_with($message, 'The route ') || str_starts_with($message, 'No query results')) {
            return self::defaultMessage($e->getStatusCode());
        }

        return $message;
    }

    private static function defaultMessage(int $status): string
    {
        return match ($status) {
            400 => 'The request could not be understood.',
            401 => 'Please sign in to continue.',
            403 => 'You do not have permission to do that.',
            404 => 'The requested resource was not found.',
            405 => 'This action is not supported.',
            409 => 'This request conflicts with the current state.',
            413 => 'The uploaded file is too large.',
            422 => 'The given data was invalid.',
            429 => 'Too many requests. Please wait a moment and try again.',
            503 => 'The service is temporarily unavailable. Please try again shortly.',
            default => 'Something went wrong on our side. Please try again.',
        };
    }
}
