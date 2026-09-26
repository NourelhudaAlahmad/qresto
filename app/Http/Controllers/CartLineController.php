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
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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
                    'menu_item_variant_id' => __(
                        'The selected variant is invalid.',
                    ),
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
                'addon_ids' => __(
                    'One or more selected add-ons are invalid.',
                ),
            ]);
        }

        if ($addons->contains(
            fn (MenuItemAddon $addon): bool => ! $addon->is_available,
        )) {
            throw ValidationException::withMessages([
                'addon_ids' => __(
                    'One or more selected add-ons are unavailable.',
                ),
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
                ->whereNull('removed_at')
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
                        'qty' => __(
                            'The total quantity may not be greater than 99.',
                        ),
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

    public function update(
        Request $request,
        CartLine $line,
    ): JsonResponse {
        /** @var TableSession $session */
        $session = $request->attributes->get('tableSession');

        $line = $this->scopedLine($session, $line);

        $validated = $request->validate([
            'qty' => ['required', 'integer', 'min:0', 'max:99'],
        ]);

        $qty = (int) $validated['qty'];

        if ($qty === 0) {
            return $this->removeLine($line);
        }

        if ($line->removed_at !== null) {
            abort(404);
        }

        $line->loadMissing('addons', 'cart');

        $unitTotal = $this->configuredUnitPrice($line);

        $line->update([
            'qty' => $qty,
            'line_total' => $unitTotal->multiply($qty),
            'undo_token' => null,
            'undo_expires_at' => null,
        ]);

        return response()->json([
            'line' => [
                'id' => $line->id,
                'qty' => $line->qty,
                'line_total' => $line->line_total->jsonSerialize(),
            ],
        ]);
    }

    public function destroy(
        Request $request,
        CartLine $line,
    ): JsonResponse {
        /** @var TableSession $session */
        $session = $request->attributes->get('tableSession');

        $line = $this->scopedLine($session, $line);

        return $this->removeLine($line);
    }

    public function restore(
        Request $request,
        CartLine $line,
    ): JsonResponse {
        /** @var TableSession $session */
        $session = $request->attributes->get('tableSession');

        $line = $this->scopedLine($session, $line);

        $validated = $request->validate([
            'undo_token' => ['required', 'string', 'max:64'],
        ]);

        if (
            $line->removed_at === null ||
            $line->undo_token === null ||
            ! hash_equals(
                $line->undo_token,
                (string) $validated['undo_token'],
            )
        ) {
            abort(404);
        }

        if (
            $line->undo_expires_at === null ||
            now()->greaterThan($line->undo_expires_at)
        ) {
            abort(410);
        }

        $line->update([
            'removed_at' => null,
            'undo_token' => null,
            'undo_expires_at' => null,
        ]);

        $line->loadMissing('addons');

        return response()->json([
            'restored' => true,
            'line' => [
                'id' => $line->id,
                'qty' => $line->qty,
                'note' => $line->note,
                'line_total' => $line->line_total->jsonSerialize(),
                'addons' => $line->addons->map(
                    fn ($addon): array => [
                        'id' => $addon->id,
                        'menu_item_addon_id' => $addon->menu_item_addon_id,
                        'label' => $addon->label_snapshot,
                        'price_delta' => $addon
                            ->price_delta
                            ->jsonSerialize(),
                    ],
                )->values()->all(),
            ],
        ]);
    }

    private function scopedLine(
        TableSession $session,
        CartLine $line,
    ): CartLine {
        $scopedLine = CartLine::query()
            ->whereKey($line->id)
            ->whereHas(
                'cart',
                fn ($query) => $query->where(
                    'table_session_id',
                    $session->id,
                ),
            )
            ->with([
                'cart',
                'addons',
            ])
            ->first();

        if ($scopedLine === null) {
            abort(404);
        }

        return $scopedLine;
    }

    private function removeLine(CartLine $line): JsonResponse
    {
        if ($line->removed_at !== null) {
            abort(404);
        }

        $undoWindowSeconds = max(
            1,
            (int) config('qresto.undo_window_seconds', 6),
        );

        $undoToken = Str::random(64);
        $undoExpiresAt = now()->addSeconds($undoWindowSeconds);

        $line->update([
            'removed_at' => now(),
            'undo_token' => $undoToken,
            'undo_expires_at' => $undoExpiresAt,
        ]);

        return response()->json([
            'removed' => true,
            'line_id' => $line->id,
            'undo_token' => $undoToken,
            'undo_expires_at' => $undoExpiresAt->toISOString(),
            'undo_window_seconds' => $undoWindowSeconds,
        ]);
    }

    private function configuredUnitPrice(CartLine $line): Money
    {
        $currency = $line->cart->currency;

        $unitTotal = Money::fromMinor(
            $line->unit_price->amount(),
            $currency,
        )->add(
            Money::fromMinor(
                $line->variant_price_delta->amount(),
                $currency,
            ),
        );

        foreach ($line->addons as $addon) {
            $unitTotal = $unitTotal->add(
                Money::fromMinor(
                    $addon->price_delta->amount(),
                    $currency,
                ),
            );
        }

        return $unitTotal;
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
