<?php

namespace App\Http\Controllers;

use App\Models\Allergen;
use App\Models\Cart;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\ServiceRequest;
use App\Models\TableSession;
use App\Support\Money;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MenuController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var TableSession $session */
        $session = $request->attributes->get('tableSession');

        $categories = MenuCategory::query()
            ->where('restaurant_id', $session->restaurant_id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->with([
                'items' => fn ($query) => $query
                    ->with([
                        'allergens',
                        'restaurant',
                    ])
                    ->orderBy('sort_order'),
            ])
            ->get()
            ->map(function (MenuCategory $category): array {
                return [
                    'id' => $category->id,
                    'name' => $category->translated_name,
                    'sort' => $category->sort_order,
                    'items' => $category->items
                        ->map(fn (MenuItem $item): array => $this->dish($item))
                        ->values()
                        ->all(),
                ];
            })
            ->values()
            ->all();

        $cart = Cart::query()
            ->where('table_session_id', $session->id)
            ->withSum('lines as item_count', 'qty')
            ->withSum('lines as subtotal_amount', 'line_total')
            ->first();

        $cartCount = $cart === null
            ? 0
            : (int) $cart->getAttribute('item_count');

        $subtotalAmount = $cart === null
            ? 0
            : (int) $cart->getAttribute('subtotal_amount');

        $cartSubtotal = $cart !== null && $cartCount > 0
            ? Money::fromMinor(
                $subtotalAmount,
                $cart->currency,
            )->jsonSerialize()
            : null;

        return Inertia::render('guest/menu', [
            'restore_guest_tab' => (bool) $request->session()->pull(
                'restore_guest_tab',
                false,
            ),

            'restaurant' => [
                'id' => $session->restaurant->id,
                'name' => $session->restaurant->name,
                'currency' => $session->restaurant->currency,
                // Replaces the hard-coded "Kitchen closes 22:30".
                'closes_today_at' => $session->restaurant->closesTodayAt(),
                'phone' => $session->restaurant->phone,
            ],

            'table' => [
                'id' => $session->table->id,
                'number' => $session->table->number,
            ],

            'session' => [
                'id' => $session->id,
                'active' => $session->isActive(),
            ],

            'latest_order_id' => Order::query()
                ->where('table_session_id', $session->id)
                ->where('table_id', $session->restaurant_table_id)
                ->where('status', '!=', 'voided')
                ->latest('id')
                ->value('id'),

            'open_requests' => ServiceRequest::query()
                ->where('table_session_id', $session->id)
                ->where('table_id', $session->restaurant_table_id)
                ->where('status', 'pending')
                ->pluck('kind')
                ->map(static fn ($kind): string => $kind instanceof \BackedEnum
                    ? (string) $kind->value
                    : (string) $kind)
                ->values()
                ->all(),

            'categories' => $categories,

            'cart' => [
                'count' => $cartCount,
                'subtotal' => $cartSubtotal,
            ],
        ]);
    }

    /**
     * One dish on the guest menu.
     *
     * @return array{id: int, name: string, description: string, price: array<string, int|string>, photo: string|null, tags: array<mixed>, allergens: array<int, array{id: int, name: string, may_contain: bool}>, available: bool, prep_minutes: int|null, flag: bool}
     */
    private function dish(MenuItem $item): array
    {
        return [
            'id' => $item->id,
            'name' => $item->translated_name,
            'description' => $item->translated_description,
            'price' => $item->price->jsonSerialize(),
            'photo' => $item->thumbUrl(),
            'tags' => $item->dietary_tags ?? [],
            'allergens' => $item->allergens
                ->map(fn (Allergen $allergen): array => $this->allergen($allergen))
                ->values()
                ->all(),
            'available' => $item->is_available,
            'prep_minutes' => $item->prep_minutes,
            'flag' => $item->chef_flag,
        ];
    }

    /**
     * An allergen on a dish, and whether it only may be present.
     *
     * @return array{id: int, name: string, may_contain: bool}
     */
    private function allergen(Allergen $allergen): array
    {
        /** @var Pivot $pivot */
        $pivot = $allergen->getRelationValue('pivot');

        return [
            'id' => $allergen->id,
            'name' => $allergen->name,
            'may_contain' => (bool) $pivot->getAttribute('may_contain'),
        ];
    }
}
