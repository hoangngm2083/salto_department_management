<?php

use App\Traits\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'abilities' => CheckAbilities::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $handler = new class
        {
            use ApiResponse;
        };

        $exceptions->shouldRenderJsonWhen(fn (Request $request) => $request->is('api/*'));

        $exceptions->render(function (ValidationException $e) use ($handler) {
            return $handler->errorResponse('Validation failed.', 422, $e->errors());
        });

        $exceptions->render(function (AuthenticationException $e) use ($handler) {
            return $handler->errorResponse('Unauthenticated.', 401);
        });

        $exceptions->render(function (AuthorizationException $e) use ($handler) {
            return $handler->errorResponse('Forbidden.', 403);
        });

        $exceptions->render(function (AccessDeniedHttpException $e) use ($handler) {
            return $handler->errorResponse('Forbidden.', 403);
        });

        $exceptions->render(function (NotFoundHttpException $e) use ($handler) {
            return $handler->errorResponse('Resource not found.', 404);
        });

        $exceptions->render(function (Throwable $e) use ($handler) {
            $status = $e instanceof HttpExceptionInterface ? $e->getStatusCode() : 500;
            $msg = config('app.debug') ? ($e->getMessage() ?: 'Server Error') : ($status === 500 ? 'Internal Server Error' : $e->getMessage());

            return $handler->errorResponse($msg, $status);
        });
    })->create();
