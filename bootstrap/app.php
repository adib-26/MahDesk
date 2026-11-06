<?php

use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);

        // Behind a load balancer or reverse proxy that terminates TLS, list its
        // addresses (or "*") in TRUSTED_PROXIES so HTTPS detection, HSTS and
        // client IPs (used by rate limits) are correct.
        if ($proxies = env('TRUSTED_PROXIES')) {
            $middleware->trustProxies(at: $proxies === '*' ? '*' : array_map('trim', explode(',', $proxies)));
        }

        $middleware->statefulApi();
        $middleware->authenticateSessions();

        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'workspace' => \App\Http\Middleware\EnsureWorkspaceAccess::class,
            'api.organization' => \App\Http\Middleware\EnsureOrganizationMember::class,
            'abilities' => \Laravel\Sanctum\Http\Middleware\CheckAbilities::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // API clients always get JSON errors, even without an Accept header.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Don't expose model class names ("No query results for model [App\Models\Ticket]").
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*') && $e->getPrevious() instanceof ModelNotFoundException) {
                return response()->json(['message' => 'Resource not found.'], 404);
            }
        });

        $exceptions->respond(function ($response, \Throwable $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson() && ! $request->header('X-Inertia')) {
                return $response;
            }

            return match ($response->getStatusCode()) {
                // CSRF token mismatch, usually an expired session. Send the user back with
                // a message instead of Inertia's raw 419 page. The request is still rejected.
                419 => back()->with('error', 'Your session expired. Please refresh the page and try again.'),
                // Rate limited: keep the user on the page and say when to retry.
                429 => back()->with('error', sprintf(
                    'Too many attempts. Please wait %d seconds and try again.',
                    (int) ($response->headers->get('Retry-After') ?: 60),
                )),
                // 413 (request over post_max_size) is left as is: it is raised before the
                // session starts, so a flash message couldn't be stored. The upload form
                // checks the total size before sending, so people normally never see it.
                default => $response,
            };
        });
    })->create();
