<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });

        $this->renderable(function (Throwable $e, $request) {
            if (! $request->expectsJson()) {
                return null;
            }

            $path = ltrim((string) $request->path(), '/');
            if (! str_starts_with($path, 'marketplace')) {
                return null;
            }

            if ($e instanceof \Illuminate\Validation\ValidationException
                || $e instanceof \Illuminate\Auth\AuthenticationException) {
                return null;
            }

            $message = trim($e->getMessage());
            if ($e instanceof \Illuminate\Session\TokenMismatchException || $message === '') {
                $message = $message !== ''
                    ? $message
                    : ($e instanceof \Illuminate\Session\TokenMismatchException
                        ? 'Session expired. Refresh the page and try again.'
                        : class_basename($e));
            }

            \Illuminate\Support\Facades\Log::error('Marketplace JSON request failed', [
                'path' => $request->path(),
                'error' => $message,
                'exception' => $e::class,
            ]);

            $status = $e instanceof \Illuminate\Session\TokenMismatchException ? 419 : 500;

            return response()->json([
                'success' => false,
                'message' => $message,
            ], $status);
        });
    }
}
