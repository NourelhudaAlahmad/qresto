<?php

namespace App\Support;

use App\Services\Auth\StaffLoginDestination;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Designed error pages (403, 404, 429, 500, 503) instead of the framework's
 * plain white ones, with a button back to the person's own screen.
 *
 * JSON requests keep their JSON errors, and a local debug session keeps the
 * full stack trace for a 500.
 */
final class ErrorPage
{
    public const STATUSES = [403, 404, 429, 500, 503];

    public static function render(Response $response, Throwable $exception, Request $request): Response
    {
        $status = $response->getStatusCode();

        if (! in_array($status, self::STATUSES, true) || $request->expectsJson()) {
            return $response;
        }

        if ($status >= 500 && app()->hasDebugModeEnabled()) {
            return $response;
        }

        app()->setLocale(self::locale($request));

        [$home, $homeLabel] = self::home($request);

        $designed = Inertia::render('errors/show', [
            'status' => $status,
            'title' => __("ui.errors.{$status}.title"),
            // A message we wrote on purpose (abort(403, 'This table is not
            // yours tonight.')) is worth showing; framework defaults are not.
            'message' => ($status === 403 ? self::ownMessage($exception) : null)
                ?? __("ui.errors.{$status}.body"),
            'home' => $home,
            'homeLabel' => $homeLabel,
            'backLabel' => __('ui.errors.go_back'),
        ])
            ->toResponse($request)
            ->setStatusCode($status);

        // Keep what the original response told the client: Retry-After and
        // the X-RateLimit-* headers on a 429, anything a 503 set, cookies.
        foreach ($response->headers->all() as $name => $values) {
            if (! str_starts_with($name, 'content-') && ! $designed->headers->has($name)) {
                $designed->headers->set($name, $values);
            }
        }

        foreach ($response->headers->getCookies() as $cookie) {
            $designed->headers->setCookie($cookie);
        }

        return $designed;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private static function home(Request $request): array
    {
        $user = $request->user();

        if ($user !== null && $user->restaurant_id !== null) {
            return [app(StaffLoginDestination::class)->for($user), __('ui.errors.back_home_staff')];
        }

        return ['/', __('ui.errors.back_home_guest')];
    }

    /**
     * Inside the web stack SetLocale has already chosen the language. An
     * unknown URL never reaches it (no route, no session), so the browser's
     * preference decides there.
     */
    private static function locale(Request $request): string
    {
        if ($request->hasSession()) {
            return app()->getLocale();
        }

        return $request->getPreferredLanguage(['en', 'ar']) ?? 'en';
    }

    private static function ownMessage(Throwable $exception): ?string
    {
        $message = trim($exception->getMessage());

        return $message === '' || $message === 'This action is unauthorized.'
            ? null
            : $message;
    }
}
