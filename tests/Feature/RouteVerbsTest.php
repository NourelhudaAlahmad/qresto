<?php

use App\Models\User;
use Illuminate\Support\Facades\Route;

/*
 * Wayfinder writes a typed helper for every route, and its TypeScript only
 * knows the classic HTTP verbs. Route::redirect() and Route::any() also
 * answer QUERY on Laravel 13.3x, and the generated types then fail
 * `npm run types:check` (composer ci:check). Keep every route to verbs
 * Wayfinder can describe.
 */
it('uses only HTTP verbs the generated route helpers can type', function () {
    $known = ['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'];

    $offending = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route) => array_diff($route->methods(), $known) !== [])
        ->map(fn ($route) => implode('|', $route->methods()).' '.$route->uri())
        ->values()
        ->all();

    expect($offending)->toBe([]);
});

it('keeps the old landings and /settings as redirects', function (string $from, string $to) {
    $user = User::factory()->create();

    $this->actingAs($user)->get($from)->assertRedirect($to);
})->with([
    ['/settings', '/settings/profile'],
    ['/app/overview', '/manager/overview'],
    ['/kitchen/pass', '/app/pass'],
    ['/waiter/orders', '/app/my-orders'],
    ['/admin/system', '/app/system'],
]);
