<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
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
            // Real heartbeats arrive with #30. Until then the gateway reports
            // its configured driver; devices report that none has checked in.
            'health' => [
                [
                    'key' => 'gateway',
                    'label' => 'Payment gateway',
                    'status' => filled(config('qresto.payments.driver')) ? 'ok' : 'down',
                    'detail' => filled(config('qresto.payments.driver')) ? 'operational' : 'not configured',
                ],
                [
                    'key' => 'kds',
                    'label' => 'Kitchen display',
                    'status' => 'unknown',
                    'detail' => 'no heartbeat yet',
                ],
                [
                    'key' => 'printer',
                    'label' => 'Receipt printer, pass',
                    'status' => 'unknown',
                    'detail' => 'no heartbeat yet',
                ],
            ],
            'currencyCode' => $restaurant->currency,
        ]);
    }
}
