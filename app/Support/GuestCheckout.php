<?php

namespace App\Support;

use App\Models\Cart;
use App\Models\Order;
use App\Models\TableSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * What the guest checkout screens share: the table session the request
 * belongs to, its cart, the payment options the restaurant offers and
 * where a placed order sends the guest.
 */
final class GuestCheckout
{
    /**
     * The table session EnsureTableSession attached to this request.
     */
    public function tableSession(Request $request): TableSession
    {
        /** @var TableSession|null $session */
        $session = $request->attributes->get('tableSession');

        abort_if($session === null, 403);

        return $session;
    }

    public function cartFor(TableSession $session): ?Cart
    {
        return Cart::query()
            ->where('restaurant_id', $session->restaurant_id)
            ->where('table_session_id', $session->id)
            ->first();
    }

    /**
     * The cart's active lines, added up.
     */
    public function subtotal(Cart $cart, string $currency): Money
    {
        $subtotal = Money::fromMinor(0, $currency);

        foreach ($cart->lines as $line) {
            if ($line->removed_at !== null) {
                continue;
            }

            $subtotal = $subtotal->add($line->line_total);
        }

        return $subtotal;
    }

    /**
     * @return list<string>
     */
    public function enabledPaymentMethods(): array
    {
        $configured = config('qresto.payments.enabled_methods', ['card', 'wallet', 'cash', 'pos']);

        if (! is_array($configured)) {
            $configured = ['card', 'wallet', 'cash', 'pos'];
        }

        return array_values(array_filter(
            $configured,
            static fn (mixed $method): bool => is_string($method),
        ));
    }

    /**
     * @return list<float>
     */
    public function tipPresets(): array
    {
        $configured = config('qresto.payments.tip_presets', [0, 10, 12.5, 15]);

        if (! is_array($configured)) {
            $configured = [0, 10, 12.5, 15];
        }

        return array_values(array_map(
            static fn (mixed $tip): float => (float) $tip,
            $configured,
        ));
    }

    public function driver(): string
    {
        return (string) config('qresto.payments.driver', 'fake');
    }

    public function statusRedirect(Order $order): RedirectResponse
    {
        return redirect()->route('guest.orders.status', $order);
    }
}
