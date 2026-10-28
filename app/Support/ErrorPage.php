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

        [$home, $homeLabel] = self::home($request);

        $designed = Inertia::render('errors/show', [
            'status' => $status,
            // A message we wrote on purpose (abort(403, 'This table is not
            // yours tonight.')) is worth showing; framework defaults are not.
            'message' => $status === 403 ? self::ownMessage($exception) : null,
            'home' => $home,
            'homeLabel' => $homeLabel,
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
            return [app(StaffLoginDestination::class)->for($user), 'Back to your screen'];
        }

        return ['/', 'Back to the start'];
    }

    private static function ownMessage(Throwable $exception): ?string
    {
        $message = trim($exception->getMessage());

        return $message === '' || $message === 'This action is unauthorized.'
            ? null
            : $message;
    }
}
