<?php

namespace App\Http\Middleware;

use App\CurrentRestaurant;
use App\Models\Restaurant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Scope every staff request to the signed-in person's restaurant.
 *
 * Guest requests get their restaurant from the table session
 * (EnsureTableSession). Staff requests had nothing setting it, so the
 * BelongsToRestaurant global scope did nothing for any staff screen and
 * each controller had to remember its own restaurant_id check. With this,
 * forgetting that check can no longer leak another restaurant's rows.
 *
 * Registered before SubstituteBindings (bootstrap/app.php), so route model
 * binding is scoped too: another restaurant's order is simply not found.
 */
class SetCurrentRestaurant
{
    public function __construct(
        private readonly CurrentRestaurant $currentRestaurant,
    ) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (
            $user !== null
            && $user->restaurant_id !== null
            && $this->currentRestaurant->id() === null
        ) {
            $restaurant = Restaurant::query()->find($user->restaurant_id);

            if ($restaurant !== null) {
                $this->currentRestaurant->set($restaurant);
            }
        }

        return $next($request);
    }
}
