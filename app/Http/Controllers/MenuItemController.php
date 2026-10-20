<?php

namespace App\Http\Controllers;

use App\Models\Allergen;
use App\Models\MenuItem;
use App\Models\MenuItemAddon;
use App\Models\MenuItemVariant;
use App\Models\TableSession;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MenuItemController extends Controller
{
    public function show(Request $request, MenuItem $menuItem): Response
    {
        /** @var TableSession $session */
        $session = $request->attributes->get('tableSession');

        abort_unless(
            $menuItem->restaurant_id === $session->restaurant_id,
            404,
        );

        $menuItem->load([
            'category',
            'restaurant',
            'variants',
            'addons',
            'allergens',
        ]);

        return Inertia::render('guest/meal', [
            'restaurant' => [
                'id' => $session->restaurant->id,
                'name' => $session->restaurant->name,
                'currency' => $session->restaurant->currency,
            ],

            'table' => [
                'id' => $session->table->id,
                'number' => $session->table->number,
            ],

            'item' => [
                'id' => $menuItem->id,
                'name' => $menuItem->translated_name,
                'description' => $menuItem->translated_description,
                'category' => $menuItem->category?->translated_name,
                'price' => $menuItem->price->jsonSerialize(),
                'photo' => $menuItem->photo_path,
                'prep_minutes' => $menuItem->prep_minutes,
                'available' => $menuItem->is_available,
                'tags' => $menuItem->dietary_tags ?? [],
                'flag' => $menuItem->chef_flag,

                'variants' => $menuItem->variants
                    ->map(function (MenuItemVariant $variant): array {
                        return [
                            'id' => $variant->id,
                            'label' => $variant->label,
                            'price_delta' => $variant->price_delta->jsonSerialize(),
                            'is_default' => $variant->is_default,
                        ];
                    })
                    ->values()
                    ->all(),

                'addons' => $menuItem->addons
                    ->map(function (MenuItemAddon $addon): array {
                        return [
                            'id' => $addon->id,
                            'label' => $addon->label,
                            'price_delta' => $addon->price_delta->jsonSerialize(),
                            'available' => $addon->is_available,
                        ];
                    })
                    ->values()
                    ->all(),

                'allergens' => $menuItem->allergens
                    ->map(function (Allergen $allergen): array {
                        /** @var Pivot $pivot */
                        $pivot = $allergen->getRelationValue('pivot');

                        return [
                            'id' => $allergen->id,
                            'name' => $allergen->name,
                            'may_contain' => (bool) $pivot->getAttribute(
                                'may_contain',
                            ),
                        ];
                    })
                    ->values()
                    ->all(),
            ],

            'translations' => [
                'back_to_menu' => __('ui.meal.back_to_menu'),
                'chef_pick' => __('ui.meal.chef_pick'),
                'prep_time' => __('ui.meal.prep_time'),
                'sold_out_message' => __('ui.meal.sold_out_message'),
                'contains' => __('ui.meal.contains'),
                'no_allergens' => __('ui.meal.no_allergens'),
                'allergens_nearby' => __('ui.meal.allergens_nearby'),
                'choose_size' => __('ui.meal.choose_size'),
                'no_size_options' => __('ui.meal.no_size_options'),
                'add_on' => __('ui.meal.add_on'),
                'optional' => __('ui.meal.optional'),
                'sold_out' => __('ui.meal.sold_out'),
                'no_addons' => __('ui.meal.no_addons'),
                'kitchen_note' => __('ui.meal.kitchen_note'),
                'note_placeholder' => __('ui.meal.note_placeholder'),
                'note_promise' => __('ui.meal.note_promise'),
                'add_to_order' => __('ui.meal.add_to_order'),
            ],
        ]);
    }
}
