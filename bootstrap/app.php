<?php

use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\EnsureStaffAccountCanAuthenticate;
use App\Http\Middleware\EnsureTableSession;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetCurrentRestaurant;
use App\Http\Middleware\SetLocale;
use App\Support\ErrorPage;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: [
            'appearance',
            'sidebar_state',
        ]);

        $middleware->alias([
            'table.session' => EnsureTableSession::class,
            'staff.auth.check' => EnsureStaffAccountCanAuthenticate::class,
        ]);

        $middleware->web(append: [
            SetCurrentRestaurant::class,
            EnsureAccountIsActive::class,
            HandleAppearance::class,
            SetLocale::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            EnsureStaffAccountCanAuthenticate::class,
        ]);

        // Scope route model binding to the signed-in person's restaurant.
        $middleware->prependToPriorityList(
            before: SubstituteBindings::class,
            prepend: SetCurrentRestaurant::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Card data must never reach the session store (sessions live in the
        // database). Laravel flashes every input on a validation error
        // except these; the card form keeps its own values client-side.
        $exceptions->dontFlash([
            'current_password',
            'password',
            'password_confirmation',
            'card_number',
            'card_expiry',
            'card_cvc',
        ]);

        // Designed 403/404/429/500/503 pages (App\Support\ErrorPage).
        $exceptions->respond(
            fn (Response $response, Throwable $exception, Request $request) => ErrorPage::render($response, $exception, $request),
        );

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*')
                || $request->expectsJson(),
        );
    })->create();
