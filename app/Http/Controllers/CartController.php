<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\CartLine;
use App\Models\TableSession;
use App\Support\Money;
use App\Support\OrderTotals;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class CartController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var TableSession $session */
        $session = $request->attributes->get('tableSession');

        $restaurant = $session->restaurant;
        $currency = $restaurant->currency;

        $cart = Cart::query()
            ->where('table_session_id', $session->id)
            ->with([
                'lines' => fn ($query) => $query
                    ->whereNull('removed_at')
                    ->with([
                        'addons',
                        'menuItem',
                    ])
                    ->orderBy('id'),
            ])
            ->first();

        /** @var array<int, array<string, mixed>> $lines */
        $lines = [];

        /** @var array<int, array{unit_price: Money, qty: int}> $totalLines */
        $totalLines = [];

        if ($cart !== null) {
            foreach ($cart->lines as $line) {
                /** @var CartLine $line */
                $addons = [];

                $configuredUnitPrice = Money::fromMinor(
                    $line->unit_price->amount(),
                    $currency,
                )->add(
                    Money::fromMinor(
                        $line->variant_price_delta->amount(),
                        $currency,
                    ),
                );

                foreach ($line->addons as $addon) {
                    $addonPrice = Money::fromMinor(
                        $addon->price_delta->amount(),
                        $currency,
                    );

                    $configuredUnitPrice = $configuredUnitPrice->add(
                        $addonPrice,
                    );

                    $addons[] = [
                        'id' => $addon->id,
                        'label' => $addon->label_snapshot,
                        'price_delta' => $addonPrice->jsonSerialize(),
                    ];
                }

                $totalLines[] = [
                    'unit_price' => $configuredUnitPrice,
                    'qty' => $line->qty,
                ];

                $photoPath = $line->menuItem?->photo_path;

                $lines[] = [
                    'id' => $line->id,
                    'menu_item_id' => $line->menu_item_id,
                    'name' => $line->name_snapshot,
                    'variant' => $line->variant_label_snapshot,
                    'qty' => $line->qty,

                    'unit_price' => $line
                        ->unit_price
                        ->jsonSerialize(),

                    'configured_unit_price' => $configuredUnitPrice
                        ->jsonSerialize(),

                    'variant_price_delta' => $line
                        ->variant_price_delta
                        ->jsonSerialize(),

                    'line_total' => $configuredUnitPrice
                        ->multiply($line->qty)
                        ->jsonSerialize(),

                    'note' => $line->note,

                    'photo_url' => $photoPath
                        ? Storage::disk('public')->url($photoPath)
                        : null,

                    'addons' => $addons,
                ];
            }
        }

        $servicePct = (string) $restaurant->service_charge_pct;

        if ($totalLines === []) {
            $zero = Money::fromMinor(0, $currency);

            $subtotal = $zero;
            $serviceAmount = $zero;
            $total = $zero;
        } else {
            $totals = OrderTotals::calculate(
                lines: $totalLines,
                servicePct: $servicePct,
            );

            $subtotal = $totals->subtotal;
            $serviceAmount = $totals->serviceAmount;
            $total = $totals->total;
        }

        return Inertia::render('guest/cart', [
            'restaurant' => [
                'id' => $restaurant->id,
                'name' => $restaurant->name,
                'currency' => $currency,
            ],

            'table' => [
                'id' => $session->table->id,
                'number' => $session->table->number,
            ],

            'guest' => [
                'name' => $session->guest_name,
                'first_name' => $this->firstName(
                    $session->guest_name,
                ),
            ],

            'cart' => [
                'id' => $cart?->id,
                'lines' => $lines,
                'note' => $cart?->note,
                'subtotal' => $subtotal->jsonSerialize(),
                'service_pct' => $servicePct,
                'service_amount' => $serviceAmount->jsonSerialize(),
                'total' => $total->jsonSerialize(),
            ],

            'undo_window_seconds' => (int) config(
                'qresto.undo_window_seconds',
                6,
            ),

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

    public function updateNote(
        Request $request,
    ): RedirectResponse {
        /** @var TableSession $session */
        $session = $request->attributes->get('tableSession');

        $validated = $request->validate([
            'note' => [
                'nullable',
                'string',
                'max:500',
            ],
        ]);

        $note = $this->sanitizeNote(
            $validated['note'] ?? null,
        );

        $cart = Cart::query()->firstOrCreate(
            [
                'table_session_id' => $session->id,
            ],
            [
                'restaurant_id' => $session->restaurant_id,
                'currency' => $session
                    ->restaurant
                    ->currency,
            ],
        );

        $cart->update([
            'note' => $note,
        ]);

        return redirect()->route('cart');
    }

    private function firstName(
        ?string $guestName,
    ): ?string {
        if ($guestName === null) {
            return null;
        }

        $guestName = trim($guestName);

        if ($guestName === '') {
            return null;
        }

        $parts = preg_split(
            '/\s+/u',
            $guestName,
        );

        return $parts[0] ?? null;
    }

    private function sanitizeNote(
        ?string $note,
    ): ?string {
        if ($note === null) {
            return null;
        }

        $note = preg_replace(
            '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u',
            '',
            $note,
        ) ?? '';

        $note = trim($note);

        return $note === ''
            ? null
            : $note;
    }
}
