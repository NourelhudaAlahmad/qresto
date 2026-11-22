<?php

namespace App\Http\Controllers;

use App\Actions\Payments\PromotionValidator;
use App\Support\GuestCheckout;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * The promo code on the checkout screen: checked against the cart and kept
 * in the session until the order is placed.
 */
class CheckoutPromoController extends Controller
{
    public function __construct(
        private readonly GuestCheckout $checkout,
    ) {}

    public function store(
        Request $request,
        PromotionValidator $promotions,
    ): RedirectResponse {
        $session = $this->checkout->tableSession($request);
        $cart = $this->checkout->cartFor($session);

        if ($cart === null) {
            throw ValidationException::withMessages([
                'promo_code' => __('messages.cart.empty'),
            ]);
        }

        $validated = $request->validate([
            'promo_code' => ['required', 'string', 'max:100'],
        ]);

        $cart->load([
            'lines' => fn ($query) => $query
                ->whereNull('removed_at')
                ->with('addons'),
        ]);

        if ($cart->lines->isEmpty()) {
            throw ValidationException::withMessages([
                'promo_code' => __('messages.cart.empty'),
            ]);
        }

        $code = trim($validated['promo_code']);

        $promotions->validate(
            restaurantId: $session->restaurant_id,
            code: $code,
            subtotal: $this->checkout->subtotal($cart, $cart->currency),
        );

        $request->session()->put('checkout.promo_code', $code);

        return redirect()->route('checkout');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->session()->forget('checkout.promo_code');

        return redirect()->route('checkout');
    }
}
