<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\CartLine;
use App\Models\MenuItem;
use App\Models\MenuItemAddon;
use App\Models\MenuItemVariant;
use App\Models\TableSession;
use App\Support\Money;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CartLineController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        /** @var TableSession $session */
        $session = $request->attributes->get('tableSession');

        $validated = $request->validate([
            'menu_item_id' => ['required', 'integer'],
            'menu_item_variant_id' => ['nullable', 'integer'],
            'addon_ids' => ['nullable', 'array'],
            'addon_ids.*' => ['integer', 'distinct'],
            'qty' => ['required', 'integer', 'min:1', 'max:99'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        /** @var MenuItem|null $item */
        $item = MenuItem::query()
            ->where('restaurant_id', $session->restaurant_id)
            ->with('restaurant')
            ->find($validated['menu_item_id']);

        if ($item === null) {
            throw ValidationException::withMessages([
                'menu_item_id' => __('The selected menu item is invalid.'),
            ]);
        }

        $availableItem = MenuItem::query()
            ->whereKey($item->id)
            ->where('restaurant_id', $session->restaurant_id)
            ->availableNow()
            ->exists();

        if (! $availableItem) {
            throw ValidationException::withMessages([
                'menu_item_id' => __('This item is currently unavailable.'),
            ]);
        }

        /** @var MenuItemVariant|null $variant */
        $variant = null;

        if ($validated['menu_item_variant_id'] ?? null) {
            /** @var MenuItemVariant|null $variant */
            $variant = MenuItemVariant::query()
                ->with('menuItem.restaurant')
                ->where('menu_item_id', $item->id)
                ->find($validated['menu_item_variant_id']);

            if ($variant === null) {
                throw ValidationException::withMessages([
                    'menu_item_variant_id' => __('The selected variant is invalid.'),
                ]);
            }
        }

        $hasVariants = MenuItemVariant::query()
            ->where('menu_item_id', $item->id)
            ->exists();

        if ($hasVariants && $variant === null) {
            throw ValidationException::withMessages([
                'menu_item_variant_id' => __('Please select a variant.'),
            ]);
        }

        /** @var array<int, int> $addonIds */
        $addonIds = $validated['addon_ids'] ?? [];

        sort($addonIds);

        /** @var Collection<int, MenuItemAddon> $addons */
        $addons = MenuItemAddon::query()
            ->with('menuItem.restaurant')
            ->where('menu_item_id', $item->id)
            ->whereIn('id', $addonIds)
            ->get();

        if ($addons->count() !== count($addonIds)) {
            throw ValidationException::withMessages([
                'addon_ids' => __('One or more selected add-ons are invalid.'),
            ]);
        }

        if ($addons->contains(
            fn (MenuItemAddon $addon): bool => ! $addon->is_available,
        )) {
            throw ValidationException::withMessages([
                'addon_ids' => __('One or more selected add-ons are unavailable.'),
            ]);
        }

        $currency = $session->restaurant->currency;

        $basePrice = Money::fromMinor(
            $item->price->amount(),
            $currency,
        );

        $variantPrice = $variant === null
            ? Money::fromMinor(0, $currency)
            : Money::fromMinor(
                $variant->price_delta->amount(),
                $currency,
            );

        $unitTotal = $basePrice->add($variantPrice);

        foreach ($addons as $addon) {
            $unitTotal = $unitTotal->add(
                Money::fromMinor(
                    $addon->price_delta->amount(),
                    $currency,
                ),
            );
        }

        $qty = (int) $validated['qty'];
        $note = $this->sanitizeNote($validated['note'] ?? null);

        DB::transaction(function () use (
            $session,
            $item,
            $variant,
            $addons,
            $addonIds,
            $basePrice,
            $variantPrice,
            $unitTotal,
            $qty,
            $note,
            $currency,
        ): void {
            $cart = Cart::query()->firstOrCreate(
                [
                    'table_session_id' => $session->id,
                ],
                [
                    'restaurant_id' => $session->restaurant_id,
                    'currency' => $currency,
                ],
            );

            $matchingLine = CartLine::query()
                ->where('cart_id', $cart->id)
                ->where('menu_item_id', $item->id)
                ->where('menu_item_variant_id', $variant?->id)
                ->where(function ($query) use ($note): void {
                    if ($note === null) {
                        $query->whereNull('note');
                    } else {
                        $query->where('note', $note);
                    }
                })
                ->with('addons')
                ->get()
                ->first(function (CartLine $line) use ($addonIds): bool {
                    $existingAddonIds = $line->addons
                        ->pluck('menu_item_addon_id')
                        ->all();

                    if (in_array(null, $existingAddonIds, true)) {
                        return false;
                    }

                    $existingAddonIds = array_map(
                        static fn ($id): int => (int) $id,
                        $existingAddonIds,
                    );

                    sort($existingAddonIds);

                    return $existingAddonIds === $addonIds;
                });

            if ($matchingLine !== null) {
                $newQty = $matchingLine->qty + $qty;

                if ($newQty > 99) {
                    throw ValidationException::withMessages([
                        'qty' => __('The total quantity may not be greater than 99.'),
                    ]);
                }

                $matchingLine->update([
                    'name_snapshot' => $item->translated_name,
                    'variant_label_snapshot' => $variant?->label,
                    'unit_price' => $basePrice,
                    'variant_price_delta' => $variantPrice,
                    'qty' => $newQty,
                    'line_total' => $unitTotal->multiply($newQty),
                    'note' => $note,
                ]);

                foreach ($matchingLine->addons as $lineAddon) {
                    $currentAddon = $addons->firstWhere(
                        'id',
                        $lineAddon->menu_item_addon_id,
                    );

                    if ($currentAddon === null) {
                        continue;
                    }

                    $lineAddon->update([
                        'label_snapshot' => $currentAddon->label,
                        'price_delta' => Money::fromMinor(
                            $currentAddon->price_delta->amount(),
                            $currency,
                        ),
                    ]);
                }

                return;
            }

            $line = CartLine::query()->create([
                'cart_id' => $cart->id,
                'menu_item_id' => $item->id,
                'menu_item_variant_id' => $variant?->id,
                'name_snapshot' => $item->translated_name,
                'variant_label_snapshot' => $variant?->label,
                'unit_price' => $basePrice,
                'variant_price_delta' => $variantPrice,
                'qty' => $qty,
                'line_total' => $unitTotal->multiply($qty),
                'note' => $note,
            ]);

            foreach ($addons as $addon) {
                $line->addons()->create([
                    'menu_item_addon_id' => $addon->id,
                    'label_snapshot' => $addon->label,
                    'price_delta' => Money::fromMinor(
                        $addon->price_delta->amount(),
                        $currency,
                    ),
                ]);
            }
        });

        return redirect()->route('cart');
    }

    private function sanitizeNote(?string $note): ?string
    {
        if ($note === null) {
            return null;
        }

        $note = preg_replace(
            '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u',
            '',
            $note,
        ) ?? '';

        $note = trim($note);

        return $note === '' ? null : $note;
    }
}
