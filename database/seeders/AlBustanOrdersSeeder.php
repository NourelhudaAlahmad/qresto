<?php

namespace Database\Seeders;

use App\Enums\OrderStatus;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\User;
use App\Support\OrderTotals;
use Illuminate\Database\Seeder;

class AlBustanOrdersSeeder extends Seeder
{
    public function run(): void
    {
        $restaurant = Restaurant::where('slug', 'al-bustan')->firstOrFail();

        $tables = $restaurant->tables()
            ->get()
            ->keyBy('number');

        $users = User::where('restaurant_id', $restaurant->id)
            ->get()
            ->keyBy('name');

        $items = MenuItem::where('restaurant_id', $restaurant->id)
            ->get()
            ->keyBy('name');

        $orders = [
            [
                'code' => '#A-1044',
                'table' => '04',
                'guest' => 'Dana',
                'waiter' => 'Nadia Rahman',
                'status' => OrderStatus::PENDING,
                'minutes_ago' => 2,
                'total' => 31.00,
                'tip' => 3.44,
                'discount' => 0,
                'paid' => false,
                'items' => [
                    [
                        'name' => 'Chicken musakhan',
                        'qty' => 1,
                    ],
                    [
                        'name' => 'Grilled flatbread',
                        'qty' => 2,
                        'note' => 'well charred',
                    ],
                ],
            ],

            [
                'code' => '#A-1043',
                'table' => '12',
                'guest' => 'Rami',
                'waiter' => 'Nadia Rahman',
                'status' => OrderStatus::PREPARING,
                'minutes_ago' => 14,
                'total' => 54.35,
                'tip' => 0,
                'discount' => 11.46,
                'paid' => true,
                'items' => [
                    [
                        'name' => 'Lamb kofta',
                        'qty' => 2,
                        'note' => 'sumac onions',
                    ],
                    [
                        'name' => 'Charred aubergine',
                        'qty' => 1,
                        'note' => 'no tahini',
                    ],
                    [
                        'name' => 'Mint lemonade',
                        'qty' => 2,
                    ],
                ],
            ],

            [
                'code' => '#A-1042',
                'table' => null,
                'guest' => 'Iris',
                'waiter' => 'Omar Faruk',
                'status' => OrderStatus::READY,
                'minutes_ago' => 9,
                'total' => 18.50,
                'tip' => 1.06,
                'discount' => 0,
                'paid' => true,
                'items' => [
                    [
                        'name' => 'Fattoush',
                        'qty' => 1,
                    ],
                    [
                        'name' => 'Cardamom coffee',
                        'qty' => 1,
                    ],
                ],
            ],

            [
                'code' => '#A-1041',
                'table' => '07',
                'guest' => 'Yara',
                'waiter' => 'Lin Wu',
                'status' => OrderStatus::SERVED,
                'minutes_ago' => 26,
                'total' => 92.20,
                'tip' => 10.07,
                'discount' => 0,
                'paid' => false,
                'items' => [
                    [
                        'name' => 'Lamb kofta',
                        'qty' => 3,
                    ],
                    [
                        'name' => 'Knafeh, orange blossom',
                        'qty' => 2,
                        'note' => 'birthday candle',
                    ],
                ],
            ],

            [
                'code' => '#A-1040',
                'table' => '02',
                'guest' => 'Sami',
                'waiter' => 'Omar Faruk',
                'status' => OrderStatus::PAID,
                'minutes_ago' => 41,
                'total' => 44.00,
                'tip' => 12.50,
                'discount' => 0,
                'paid' => true,
                'items' => [
                    [
                        'name' => 'Labneh & za’atar',
                        'qty' => 2,
                    ],
                    [
                        'name' => 'Mint lemonade',
                        'qty' => 2,
                    ],
                ],
            ],
        ];

        foreach ($orders as $data) {
            $table = $data['table'] !== null
                ? $tables->get($data['table'])
                : null;

            $waiter = $users->get($data['waiter']);

            $lineData = [];

            foreach ($data['items'] as $line) {
                $menuItem = $items->get($line['name']);

                if (! $menuItem) {
                    throw new \RuntimeException(
                        "Menu item [{$line['name']}] was not found."
                    );
                }

                $lineData[] = [
                    'menu_item' => $menuItem,
                    'qty' => $line['qty'],
                    'note' => $line['note'] ?? null,
                ];
            }

            $totals = OrderTotals::calculate(
                collect($lineData)
                    ->map(fn (array $line) => [
                        'unit_price' => $line['menu_item']->price->amount() / 100,
                        'qty' => $line['qty'],
                    ])
                    ->all(),
                (float) $restaurant->service_charge_pct,
                $data['tip'],
                $data['discount'],
            );

            if (round($totals->total, 2) !== round($data['total'], 2)) {
                throw new \RuntimeException(
                    "Order {$data['code']} total mismatch. ".
                    "Expected {$data['total']}, calculated {$totals->total}."
                );
            }

            $order = Order::updateOrCreate(
                [
                    'restaurant_id' => $restaurant->id,
                    'code' => $data['code'],
                ],
                [
                    'table_id' => $table?->id,
                    'table_session_id' => null,
                    'guest_name' => $data['guest'],
                    'assigned_user_id' => $waiter?->id,
                    'status' => OrderStatus::PLACED,
                    'placed_at' => now()->subMinutes($data['minutes_ago']),
                    'subtotal' => $totals->subtotal,
                    'service_pct' => $restaurant->service_charge_pct,
                    'service_amount' => $totals->serviceAmount,
                    'tip_amount' => $data['tip'],
                    'discount_amount' => $data['discount'],
                    'total' => $totals->total,
                    'is_paid' => false,
                    'paid_at' => null,
                ],
            );
            $order->lines()->delete();
            $order->payments()->delete();
            $order->events()->delete();
            foreach ($lineData as $line) {
                $menuItem = $line['menu_item'];

                $unitPrice = $menuItem->price->amount() / 100;
                $lineTotal = round(
                    $unitPrice * $line['qty'],
                    2,
                );

                $order->lines()->create([
                    'menu_item_id' => $menuItem->id,
                    'name_snapshot' => $menuItem->name,
                    'unit_price' => $unitPrice,
                    'qty' => $line['qty'],
                    'line_total' => $lineTotal,
                    'note' => $line['note'],
                    'station' => $this->stationFor($menuItem->name),
                ]);
            }

            $this->transitionOrder(
                $order,
                $data['status'],
                $waiter,
            );

            if ($data['paid']) {
                $order->update([
                    'is_paid' => true,
                    'paid_at' => now()->subMinutes($data['minutes_ago']),
                ]);

                $order->payments()->create([
                    'method' => 'card',
                    'status' => 'paid',
                    'amount' => $data['total'],
                    'tip_amount' => $data['tip'],
                    'gateway' => 'demo',
                    'gateway_intent_id' => 'demo_'.strtolower(str_replace('#', '', $data['code'])),
                    'gateway_status' => 'succeeded',
                    'requires_3ds' => false,
                    'failure_reason' => null,
                    'taken_by' => $waiter?->id,
                    'paid_at' => now()->subMinutes($data['minutes_ago']),
                    'refunded_amount' => 0,
                    'refunded_at' => null,
                ]);
            }
        }
    }

    private function transitionOrder(
        Order $order,
        OrderStatus $target,
        ?User $actor,
    ): void {
        $transitions = [
            OrderStatus::PLACED->value => [
                OrderStatus::PENDING,
            ],

            OrderStatus::PENDING->value => [
                OrderStatus::PENDING,
            ],

            OrderStatus::PREPARING->value => [
                OrderStatus::PENDING,
                OrderStatus::PREPARING,
            ],

            OrderStatus::READY->value => [
                OrderStatus::PENDING,
                OrderStatus::PREPARING,
                OrderStatus::READY,
            ],

            OrderStatus::SERVED->value => [
                OrderStatus::PENDING,
                OrderStatus::PREPARING,
                OrderStatus::READY,
                OrderStatus::SERVED,
            ],

            OrderStatus::PAID->value => [
                OrderStatus::PENDING,
                OrderStatus::PREPARING,
                OrderStatus::READY,
                OrderStatus::SERVED,
                OrderStatus::PAID,
            ],
        ];

        foreach ($transitions[$target->value] ?? [] as $status) {
            $order->transitionTo($status, $actor);
        }
    }

    private function stationFor(string $menuItem): string
    {
        return match ($menuItem) {
            'Mint lemonade',
            'Cardamom coffee' => 'bar',

            'Knafeh, orange blossom' => 'dessert',

            'Lamb kofta',
            'Chicken musakhan',
            'Grilled flatbread' => 'grill',

            default => 'kitchen',
        };
    }
}
