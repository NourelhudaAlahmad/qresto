<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Menu\StoreMenuPhoto;
use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Menu\CategoryRequest;
use App\Http\Requests\Menu\DishRequest;
use App\Http\Requests\Menu\ReorderCategoriesRequest;
use App\Models\Allergen;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Restaurant;
use App\Models\User;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Menu management (#31): categories, dishes and the editor.
 *
 * Every route is behind can:edit_menu_prices. Availability has its own
 * endpoint and is the only thing that saves immediately; everything else
 * is an explicit Save.
 */
class MenuAdminController extends Controller
{
    private const OPEN_ORDER_EXCLUDED = [OrderStatus::PAID, OrderStatus::CANCELLED];

    public function index(Request $request): Response
    {
        $restaurant = $this->restaurant($request);

        $categories = MenuCategory::query()
            ->where('restaurant_id', $restaurant->id)
            ->withCount('items')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $items = MenuItem::query()
            ->where('restaurant_id', $restaurant->id)
            ->with(['variants', 'addons', 'allergens'])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $soldToday = $this->soldToday($restaurant);

        $editors = User::query()
            ->whereIn('id', $items->pluck('updated_by')->filter()->unique()->all())
            ->pluck('name', 'id');

        $currency = (string) $restaurant->currency;

        return Inertia::render('staff/admin/menu', [
            'currency' => $currency,
            'categories' => $categories->map(fn (MenuCategory $category): array => [
                'id' => (int) $category->id,
                'name' => $category->name,
                'dish_count' => (int) $category->items_count,
            ])->values()->all(),
            'dishes' => $items->map(fn (MenuItem $item): array => [
                'id' => (int) $item->id,
                'menu_category_id' => (int) $item->menu_category_id,
                'name' => $item->name,
                'description' => $item->description,
                'price' => $this->decimal($item->price),
                'price_formatted' => $item->price->formatted(),
                'prep_minutes' => $item->prep_minutes,
                'available' => (bool) $item->is_available,
                'chef_flag' => (bool) $item->chef_flag,
                'dietary_tags' => array_values((array) $item->dietary_tags),
                'photo' => $item->photoUrl(),
                'thumb' => $item->thumbUrl(),
                'sold_today' => (int) ($soldToday[$item->id] ?? 0),
                'allergens' => $item->allergens->map(fn (Allergen $allergen): array => [
                    'id' => (int) $allergen->id,
                    'name' => $allergen->name,
                    // The pivot from MenuItem::allergens()->withPivot('may_contain').
                    'may_contain' => (bool) $allergen->getRelationValue('pivot')?->getAttribute('may_contain'),
                ])->values()->all(),
                'variants' => $item->variants->map(fn ($variant): array => [
                    'id' => (int) $variant->id,
                    'label' => $variant->label,
                    'price_delta' => $this->decimal($variant->price_delta),
                    'is_default' => (bool) $variant->is_default,
                ])->values()->all(),
                'addons' => $item->addons->map(fn ($addon): array => [
                    'id' => (int) $addon->id,
                    'label' => $addon->label,
                    'price_delta' => $this->decimal($addon->price_delta),
                    'is_available' => (bool) $addon->is_available,
                ])->values()->all(),
                'updated_at' => $item->updated_at?->toIso8601String(),
                'updated_by' => $item->updated_by === null ? null : ($editors[$item->updated_by] ?? null),
            ])->values()->all(),
            'allergens' => Allergen::query()
                ->where('restaurant_id', $restaurant->id)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Allergen $allergen): array => ['id' => (int) $allergen->id, 'name' => $allergen->name])
                ->values()
                ->all(),
            'photoRules' => [
                'min_width' => (int) config('qresto.menu.photo_min_width', 1200),
                'ratio' => '4:3',
            ],
        ]);
    }

    // ── Categories ───────────────────────────────────────────────────

    public function storeCategory(CategoryRequest $request): RedirectResponse
    {
        $restaurant = $this->restaurant($request);

        MenuCategory::query()->create([
            'restaurant_id' => $restaurant->id,
            'name' => trim((string) $request->validated('name')),
            'sort_order' => (int) MenuCategory::query()->where('restaurant_id', $restaurant->id)->max('sort_order') + 1,
            'is_active' => true,
        ]);

        return back()->with('success', 'Category added.');
    }

    public function updateCategory(CategoryRequest $request, MenuCategory $category): RedirectResponse
    {
        $this->own($request, $category->restaurant_id);

        $category->update(['name' => trim((string) $request->validated('name'))]);

        return back()->with('success', 'Category renamed.');
    }

    public function destroyCategory(Request $request, MenuCategory $category): RedirectResponse
    {
        $this->own($request, $category->restaurant_id);

        if ($category->items()->exists()) {
            throw ValidationException::withMessages([
                'category' => 'Move or delete its dishes first.',
            ]);
        }

        $category->delete();

        return back()->with('success', 'Category deleted.');
    }

    /**
     * The guest menu follows this order: it sorts sections by sort_order.
     */
    public function reorderCategories(ReorderCategoriesRequest $request): RedirectResponse
    {
        $restaurant = $this->restaurant($request);

        DB::transaction(function () use ($request, $restaurant): void {
            foreach (array_values((array) $request->validated('ids')) as $position => $id) {
                MenuCategory::query()
                    ->where('restaurant_id', $restaurant->id)
                    ->whereKey((int) $id)
                    ->update(['sort_order' => $position + 1]);
            }
        });

        return back();
    }

    // ── Dishes ───────────────────────────────────────────────────────

    public function storeItem(DishRequest $request, StoreMenuPhoto $photos): RedirectResponse
    {
        $restaurant = $this->restaurant($request);

        $item = DB::transaction(function () use ($request, $restaurant): MenuItem {
            $item = new MenuItem([
                'restaurant_id' => $restaurant->id,
                'is_available' => true,
                'sort_order' => (int) MenuItem::query()
                    ->where('menu_category_id', (int) $request->validated('menu_category_id'))
                    ->max('sort_order') + 1,
            ]);

            $this->fill($item, $request);
            $this->syncOptions($item, $request);

            return $item;
        });

        if ($request->hasFile('photo')) {
            $photos->handle($item, $request->file('photo'));
        }

        return back()->with('success', "{$item->name} added.")->with('created_dish_id', $item->id);
    }

    public function updateItem(DishRequest $request, MenuItem $item, StoreMenuPhoto $photos): RedirectResponse
    {
        $this->own($request, $item->restaurant_id);

        DB::transaction(function () use ($request, $item): void {
            $this->fill($item, $request);
            $this->syncOptions($item, $request);
        });

        if ($request->hasFile('photo')) {
            $photos->handle($item, $request->file('photo'));
        }

        return back()->with('success', "{$item->name} saved.");
    }

    /**
     * The only immediate-save endpoint: "no more bass" during service.
     */
    public function availability(Request $request, MenuItem $item): RedirectResponse
    {
        $this->own($request, $item->restaurant_id);

        $available = (bool) $request->validate(['available' => ['required', 'boolean']])['available'];

        $item->forceFill([
            'is_available' => $available,
            'updated_by' => $request->user()?->getKey(),
        ])->save();

        return back()->with('success', $available ? "{$item->name} is back on." : "{$item->name} is sold out.");
    }

    /**
     * Refused while the dish is on an open order. Otherwise a soft delete:
     * historic order lines keep their name and price snapshots.
     */
    public function destroyItem(Request $request, MenuItem $item): RedirectResponse
    {
        $this->own($request, $item->restaurant_id);

        $onOpenOrder = DB::table('order_lines')
            ->join('orders', 'orders.id', '=', 'order_lines.order_id')
            ->where('order_lines.menu_item_id', $item->id)
            ->whereNotIn('orders.status', array_map(fn (OrderStatus $status): string => $status->value, self::OPEN_ORDER_EXCLUDED))
            ->exists();

        if ($onOpenOrder) {
            throw ValidationException::withMessages([
                'dish' => "{$item->name} is on an open order. Delete it once those orders are paid.",
            ]);
        }

        $item->forceFill(['updated_by' => $request->user()?->getKey()])->saveQuietly();
        $item->delete();

        return back()->with('success', "{$item->name} deleted.");
    }

    // ── Helpers ──────────────────────────────────────────────────────

    private function fill(MenuItem $item, DishRequest $request): void
    {
        $validated = $request->validated();

        $item->fill([
            'menu_category_id' => (int) $validated['menu_category_id'],
            'name' => trim((string) $validated['name']),
            'description' => isset($validated['description']) ? trim((string) $validated['description']) : null,
            'price' => (string) $validated['price'],
            'prep_minutes' => $validated['prep_minutes'] ?? null,
            'chef_flag' => (bool) ($validated['chef_flag'] ?? $item->chef_flag ?? false),
            'dietary_tags' => array_key_exists('dietary_tags', $validated)
                ? array_values(array_unique(array_map('strtolower', (array) $validated['dietary_tags'])))
                : $item->dietary_tags,
        ]);

        $item->updated_by = $request->user()?->getKey();
        $item->save();
    }

    private function syncOptions(MenuItem $item, DishRequest $request): void
    {
        $validated = $request->validated();

        if (array_key_exists('variants', $validated)) {
            $keep = [];

            foreach (array_values((array) $validated['variants']) as $position => $row) {
                $variant = $item->variants()->updateOrCreate(
                    ['id' => $row['id'] ?? null, 'menu_item_id' => $item->id],
                    [
                        'label' => trim((string) $row['label']),
                        'price_delta' => (string) $row['price_delta'],
                        'is_default' => (bool) ($row['is_default'] ?? false),
                        'sort_order' => $position + 1,
                    ],
                );
                $keep[] = $variant->id;
            }

            $item->variants()->whereNotIn('id', $keep)->delete();
        }

        if (array_key_exists('addons', $validated)) {
            $keep = [];

            foreach (array_values((array) $validated['addons']) as $position => $row) {
                $addon = $item->addons()->updateOrCreate(
                    ['id' => $row['id'] ?? null, 'menu_item_id' => $item->id],
                    [
                        'label' => trim((string) $row['label']),
                        'price_delta' => (string) $row['price_delta'],
                        'is_available' => (bool) ($row['is_available'] ?? true),
                        'sort_order' => $position + 1,
                    ],
                );
                $keep[] = $addon->id;
            }

            $item->addons()->whereNotIn('id', $keep)->delete();
        }

        if (array_key_exists('allergens', $validated)) {
            $sync = [];

            foreach ((array) $validated['allergens'] as $row) {
                $allergen = isset($row['id'])
                    ? Allergen::query()->where('restaurant_id', $item->restaurant_id)->findOrFail((int) $row['id'])
                    : Allergen::query()->firstOrCreate([
                        'restaurant_id' => $item->restaurant_id,
                        'name' => trim((string) $row['name']),
                    ]);

                $sync[$allergen->id] = ['may_contain' => (bool) ($row['may_contain'] ?? false)];
            }

            $item->allergens()->sync($sync);
        }
    }

    /**
     * @return array<int, int> menu_item_id => quantity sold today
     */
    private function soldToday(Restaurant $restaurant): array
    {
        $today = now()->timezone($restaurant->timezone ?: (string) config('app.timezone'))->toDateString();

        return DB::table('order_lines')
            ->join('orders', 'orders.id', '=', 'order_lines.order_id')
            ->where('orders.restaurant_id', $restaurant->id)
            ->whereDate('orders.service_date', $today)
            ->where('orders.status', '!=', OrderStatus::CANCELLED->value)
            ->groupBy('order_lines.menu_item_id')
            ->selectRaw('order_lines.menu_item_id AS id, SUM(order_lines.qty) AS sold')
            ->pluck('sold', 'id')
            ->map(fn ($sold): int => (int) $sold)
            ->all();
    }

    private function decimal(Money $money): string
    {
        return number_format($money->amount() / 100, 2, '.', '');
    }

    private function restaurant(Request $request): Restaurant
    {
        /** @var User $user */
        $user = $request->user();

        $restaurant = $user->restaurant;

        abort_unless($restaurant instanceof Restaurant, 403);

        return $restaurant;
    }

    private function own(Request $request, int|string|null $restaurantId): void
    {
        abort_unless((int) $restaurantId === (int) $this->restaurant($request)->id, 404);
    }
}
