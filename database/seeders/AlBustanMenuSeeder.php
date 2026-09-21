<?php

namespace Database\Seeders;

use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Restaurant;
use Illuminate\Database\Seeder;

class AlBustanMenuSeeder extends Seeder
{
    public function run(): void
    {
        $restaurant = Restaurant::where('name', 'Al Bustan')->firstOrFail();

        $categoryData = [
            'small' => [
                'name' => 'Small plates',
                'translations' => [
                    'en' => 'Small plates',
                    'ar' => 'أطباق صغيرة',
                ],
                'sort_order' => 1,
                'is_active' => true,
            ],
            'grill' => [
                'name' => 'From the grill',
                'translations' => [
                    'en' => 'From the grill',
                    'ar' => 'من المشواة',
                ],
                'sort_order' => 2,
                'is_active' => true,
            ],
            'sides' => [
                'name' => 'Sides',
                'translations' => [
                    'en' => 'Sides',
                    'ar' => 'أطباق جانبية',
                ],
                'sort_order' => 3,
                'is_active' => true,
            ],
            'sweet' => [
                'name' => 'Sweet',
                'translations' => [
                    'en' => 'Sweet',
                    'ar' => 'حلويات',
                ],
                'sort_order' => 4,
                'is_active' => true,
            ],
            'drinks' => [
                'name' => 'Drinks',
                'translations' => [
                    'en' => 'Drinks',
                    'ar' => 'مشروبات',
                ],
                'sort_order' => 5,
                'is_active' => true,
            ],
        ];

        $categories = [];

        foreach ($categoryData as $key => $data) {
            $categories[$key] = MenuCategory::updateOrCreate(
                [
                    'restaurant_id' => $restaurant->id,
                    'name' => $data['name'],
                ],
                [
                    'translations' => $data['translations'],
                    'sort_order' => $data['sort_order'],
                    'is_active' => $data['is_active'],
                ],
            );
        }

        $items = [
            [
                'category' => 'small',
                'name' => 'Charred aubergine',
                'description' => 'Smoked over coals, tahini, pomegranate, mint.',
                'price' => 1250,
                'dietary_tags' => ['vegan'],
                'chef_flag' => false,
                'is_available' => true,
                'sort_order' => 1,
            ],
            [
                'category' => 'small',
                'name' => 'Labneh & za’atar',
                'description' => 'Strained yoghurt, wild thyme, olive oil, warm flatbread.',
                'price' => 900,
                'dietary_tags' => ['vegetarian'],
                'chef_flag' => false,
                'is_available' => true,
                'sort_order' => 2,
            ],
            [
                'category' => 'small',
                'name' => 'Fattoush',
                'description' => 'Sumac, radish, cucumber, torn bread, pomegranate molasses.',
                'price' => 1100,
                'dietary_tags' => ['vegan'],
                'chef_flag' => false,
                'is_available' => true,
                'sort_order' => 3,
            ],
            [
                'category' => 'grill',
                'name' => 'Lamb kofta',
                'description' => 'Charcoal-grilled, sumac onions, tahini, flatbread.',
                'price' => 1800,
                'dietary_tags' => [],
                'chef_flag' => true,
                'is_available' => true,
                'sort_order' => 1,
            ],
            [
                'category' => 'grill',
                'name' => 'Chicken musakhan',
                'description' => 'Slow-roasted, caramelised onion, pine nuts, taboon bread.',
                'price' => 1650,
                'dietary_tags' => [],
                'chef_flag' => false,
                'is_available' => true,
                'sort_order' => 2,
            ],
            [
                'category' => 'grill',
                'name' => 'Whole sea bass',
                'description' => 'Charcoal-grilled with lemon and chermoula.',
                'price' => 2600,
                'dietary_tags' => [],
                'chef_flag' => false,
                'is_available' => false,
                'sort_order' => 3,
            ],
            [
                'category' => 'sides',
                'name' => 'Burnt-butter rice',
                'description' => 'Vermicelli, toasted almond, crisp shallot.',
                'price' => 650,
                'dietary_tags' => ['vegetarian'],
                'chef_flag' => false,
                'is_available' => true,
                'sort_order' => 1,
            ],
            [
                'category' => 'sides',
                'name' => 'Grilled flatbread',
                'description' => 'Straight off the taboon, brushed with olive oil.',
                'price' => 400,
                'dietary_tags' => ['vegan'],
                'chef_flag' => false,
                'is_available' => true,
                'sort_order' => 2,
            ],
            [
                'category' => 'sweet',
                'name' => 'Knafeh, orange blossom',
                'description' => 'Shredded pastry, sweet cheese, pistachio, syrup.',
                'price' => 950,
                'dietary_tags' => ['vegetarian'],
                'chef_flag' => false,
                'is_available' => true,
                'sort_order' => 1,
            ],
            [
                'category' => 'drinks',
                'name' => 'Mint lemonade',
                'description' => 'Pressed to order, plenty of ice.',
                'price' => 500,
                'dietary_tags' => ['vegan'],
                'chef_flag' => false,
                'is_available' => true,
                'sort_order' => 1,
            ],
            [
                'category' => 'drinks',
                'name' => 'Cardamom coffee',
                'description' => 'Brewed dark, served small.',
                'price' => 450,
                'dietary_tags' => [],
                'chef_flag' => false,
                'is_available' => true,
                'sort_order' => 2,
            ],
        ];

        foreach ($items as $itemData) {
            $category = $categories[$itemData['category']];

            $item = MenuItem::updateOrCreate(
                [
                    'restaurant_id' => $restaurant->id,
                    'name' => $itemData['name'],
                ],
                [
                    'menu_category_id' => $category->id,
                    'description' => $itemData['description'],
                    'translations' => [
                        'en' => $itemData['name'],
                        'ar' => $itemData['name'],
                    ],
                    'price' => $itemData['price'],
                    'photo_path' => null,
                    'prep_minutes' => 18,
                    'is_available' => $itemData['is_available'],
                    'is_scheduled' => false,
                    'available_from' => null,
                    'available_until' => null,
                    'sort_order' => $itemData['sort_order'],
                    'dietary_tags' => $itemData['dietary_tags'],
                    'chef_flag' => $itemData['chef_flag'],
                    'updated_by' => null,
                ],
            );

            if ($item->name === 'Lamb kofta') {
                $addons = [
                    [
                        'label' => 'Extra taboon bread',
                        'price_delta' => 200,
                        'is_available' => true,
                        'sort_order' => 1,
                    ],
                    [
                        'label' => 'Tahini, extra',
                        'price_delta' => 150,
                        'is_available' => true,
                        'sort_order' => 2,
                    ],
                ];

                foreach ($addons as $addon) {
                    $item->addons()->updateOrCreate(
                        [
                            'label' => $addon['label'],
                        ],
                        [
                            'price_delta' => $addon['price_delta'],
                            'is_available' => $addon['is_available'],
                            'sort_order' => $addon['sort_order'],
                        ],
                    );
                }
            }
        }
    }
}
