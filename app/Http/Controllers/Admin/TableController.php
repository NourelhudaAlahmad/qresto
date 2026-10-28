<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Tables\ManageTables;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TableRequest;
use App\Models\RestaurantTable;
use App\Models\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Tables and their QR stickers (#29). Every route is behind
 * can:manage_restaurant_config, and route binding is already scoped to the
 * admin's restaurant (SetCurrentRestaurant) and to active tables.
 */
class TableController extends Controller
{
    public function __construct(
        private readonly ManageTables $tables,
    ) {}

    public function store(TableRequest $request): RedirectResponse
    {
        /** @var User $admin */
        $admin = $request->user();

        $table = $this->tables->add($admin, $this->data($request));

        return back()->with('success', "Table {$table->number} added.");
    }

    public function update(TableRequest $request, RestaurantTable $table): RedirectResponse
    {
        $this->tables->update($table, $this->data($request));

        return back()->with('success', "Table {$table->number} saved.");
    }

    public function retire(RestaurantTable $table): RedirectResponse
    {
        $this->tables->retire($table);

        return back()->with('success', "Table {$table->number} retired.");
    }

    public function reissue(RestaurantTable $table): RedirectResponse
    {
        $this->tables->reissue($table);

        return back()->with('success', "New code for Table {$table->number}. Print and replace the sticker.");
    }

    /**
     * Printable sheet: one card per table, or one table with ?table=ID.
     */
    public function qrSheet(Request $request): View
    {
        /** @var User $admin */
        $admin = $request->user();

        $tables = RestaurantTable::query()
            ->where('restaurant_id', $admin->restaurant_id)
            ->when($request->filled('table'), fn ($query) => $query->whereKey((int) $request->query('table')))
            ->orderBy('sort_order')
            ->orderBy('number')
            ->get();

        abort_if($tables->isEmpty(), 404);

        $writer = new Writer(new ImageRenderer(new RendererStyle(240, 1), new SvgImageBackEnd));

        return view('qr-sheet', [
            'restaurant' => $admin->restaurant,
            'cards' => $tables->map(fn (RestaurantTable $table): array => [
                'number' => (string) $table->number,
                'url' => route('table.show', $table->qr_token),
                'svg' => $writer->writeString(route('table.show', $table->qr_token)),
            ]),
        ]);
    }

    /**
     * @return array{number: string, seats: int, sort_order: int|null}
     */
    private function data(TableRequest $request): array
    {
        return [
            'number' => trim((string) $request->validated('number')),
            'seats' => (int) $request->validated('seats'),
            'sort_order' => $request->validated('sort_order') === null ? null : (int) $request->validated('sort_order'),
        ];
    }
}
