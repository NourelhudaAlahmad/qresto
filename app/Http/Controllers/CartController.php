<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\CartLine;
use App\Models\TableSession;
use App\Support\Money;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CartController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var TableSession $session */
        $session = $request->attributes->get('tableSession');

        $cart = Cart::query()
            ->where('table_session_id', $session->id)
            ->with([
                'lines' => fn ($query) => $query
                    ->with('addons')
                    ->orderBy('id'),
            ])
            ->first();

        /** @var array<int, array<string, mixed>> $lines */
        $lines = [];

        if ($cart !== null) {
            foreach ($cart->lines as $line) {
                /** @var CartLine $line */
                $addons = [];

                foreach ($line->addons as $addon) {
                    $addons[] = [
                        'id' => $addon->id,
                        'label' => $addon->label_snapshot,
                        'price_delta' => $addon->price_delta->jsonSerialize(),
                    ];
                }

                $lines[] = [
                    'id' => $line->id,
                    'name' => $line->name_snapshot,
                    'variant' => $line->variant_label_snapshot,
                    'qty' => $line->qty,
                    'unit_price' => $line->unit_price->jsonSerialize(),
                    'variant_price_delta' => $line
                        ->variant_price_delta
                        ->jsonSerialize(),
                    'line_total' => $line->line_total->jsonSerialize(),
                    'note' => $line->note,
                    'addons' => $addons,
                ];
            }
        }

        $subtotalAmount = $cart === null
            ? 0
            : (int) $cart->lines->sum(
                fn (CartLine $line): int => $line->line_total->amount(),
            );

        return Inertia::render('guest/cart', [
            'restaurant' => [
                'id' => $session->restaurant->id,
                'name' => $session->restaurant->name,
                'currency' => $session->restaurant->currency,
            ],

            'table' => [
                'id' => $session->table->id,
                'number' => $session->table->number,
            ],

            'cart' => [
                'id' => $cart?->id,
                'lines' => $lines,
                'subtotal' => Money::fromMinor(
                    $subtotalAmount,
                    $session->restaurant->currency,
                )->jsonSerialize(),
            ],

            'translations' => [
                'title' => __('ui.cart.title'),
                'empty' => __('ui.cart.empty'),
                'back_to_menu' => __('ui.cart.back_to_menu'),
                'subtotal' => __('ui.cart.subtotal'),
                'quantity' => __('ui.cart.quantity'),
                'note' => __('ui.cart.note'),
            ],
        ]);
    }
}
