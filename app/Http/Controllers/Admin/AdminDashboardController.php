<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Integration;
use App\Models\Restaurant;
use App\Models\User;
use App\Services\Admin\AdminDashboardSummary;
use App\Services\Admin\PermissionMatrix;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Super admin dashboard (#28): money, menu health and who can do what.
 * The only screen where the permission matrix is editable.
 */
class AdminDashboardController extends Controller
{
    private const LOCALE_NAMES = [
        'en' => 'English',
        'ar' => 'العربية',
        'tr' => 'Türkçe',
    ];

    public function index(
        Request $request,
        AdminDashboardSummary $summary,
        PermissionMatrix $matrix,
    ): Response {
        /** @var User $admin */
        $admin = $request->user();

        /** @var Restaurant|null $restaurant */
        $restaurant = $admin->restaurant;

        abort_unless($restaurant !== null, 403);

        $locales = (array) ($restaurant->supported_locales ?? []);

        return Inertia::render('staff/admin/system', [
            'summary' => $summary->for($restaurant),
            'matrix' => $matrix->for($admin),
            'audits' => $matrix->recentAudits(),
            'restaurant' => [
                'name' => $restaurant->name,
                'service_charge_pct' => (float) $restaurant->service_charge_pct,
                'currency' => $restaurant->currency,
                'languages' => array_values(array_map(
                    fn ($locale): string => self::LOCALE_NAMES[(string) $locale] ?? (string) $locale,
                    $locales,
                )),
                'tables_count' => $restaurant->tables()->count(),
            ],
            // Live system health (#30), derived from heartbeat age.
            'health' => fn (): array => $this->health($restaurant),
            'currencyCode' => $restaurant->currency,
        ]);
    }

    /**
     * @return list<array{id: int, kind: string, name: string, health: string, detail: string|null, last_heartbeat_at: string|null}>
     */
    private function health(Restaurant $restaurant): array
    {
        $order = array_flip(Integration::KINDS);

        return array_values(Integration::query()
            ->where('restaurant_id', $restaurant->id)
            ->get()
            ->sortBy(fn (Integration $integration): string => ($order[$integration->kind] ?? 9).$integration->name)
            ->map(fn (Integration $integration): array => [
                'id' => (int) $integration->id,
                'kind' => $integration->kind,
                'name' => $integration->name,
                'health' => $integration->health(),
                'detail' => $integration->detail,
                'last_heartbeat_at' => $integration->last_heartbeat_at?->toIso8601String(),
            ])
            ->all());
    }
}
