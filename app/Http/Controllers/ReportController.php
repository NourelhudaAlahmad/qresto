<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReportRequest;
use App\Models\ReportExport;
use App\Models\Restaurant;
use App\Models\User;
use App\Services\Reports\SalesReport;
use Carbon\CarbonImmutable;
use Dompdf\Dompdf;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Reports (#32), behind can:financial_reports. The screen, the CSV and the
 * PDF all come from the same SalesReport, so an export always matches what
 * is on screen.
 */
class ReportController extends Controller
{
    private const MAX_DAYS = 366;

    public function __construct(
        private readonly SalesReport $report,
    ) {}

    public function index(ReportRequest $request): Response
    {
        [$restaurant, $range, $data] = $this->load($request);

        return Inertia::render('staff/reports', [
            'range' => [
                'start' => $range['start']->toDateString(),
                'end' => $range['end']->toDateString(),
                'preset' => $range['preset'],
            ],
            'currency' => (string) $restaurant->currency,
            ...$data,
        ]);
    }

    /**
     * Streamed so a long range never sits in memory as one string.
     */
    public function csv(ReportRequest $request): StreamedResponse
    {
        [$restaurant, $range, $data] = $this->load($request);

        $this->record($request, $restaurant, $range, 'csv', count($data['rows']));

        $filename = sprintf('qresto-report-%s-to-%s.csv', $range['start']->toDateString(), $range['end']->toDateString());

        return response()->streamDownload(function () use ($data): void {
            $out = fopen('php://output', 'w');

            if ($out === false) {
                return;
            }

            fputcsv($out, ['Date', 'Waiter', 'Covers', 'Orders', 'Net', 'Tips', 'Method'], escape: '\\');

            foreach ($data['rows'] as $row) {
                fputcsv($out, [
                    $row['date'],
                    $row['waiter'],
                    $row['covers'],
                    $row['orders'],
                    self::decimal($row['net_minor']),
                    self::decimal($row['tips_minor']),
                    SalesReport::methodLabel($row['method']),
                ], escape: '\\');
            }

            $totals = $data['totals'];

            fputcsv($out, [
                'Total', '', $totals['covers'], $totals['orders'],
                self::decimal($totals['net_minor']), self::decimal($totals['tips_minor']), '',
            ], escape: '\\');

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Rendered from a print-styled Blade view. Needs dompdf/dompdf.
     */
    public function pdf(ReportRequest $request): HttpResponse
    {
        if (! class_exists(Dompdf::class)) {
            abort(501, 'PDF export needs dompdf. Run: composer require dompdf/dompdf');
        }

        [$restaurant, $range, $data] = $this->load($request);

        $html = view('reports.print', [
            'restaurant' => $restaurant,
            'range' => $range,
            'rows' => $data['rows'],
            'totals' => $data['totals'],
            'generatedAt' => now()->timezone($restaurant->timezone ?: (string) config('app.timezone')),
            'money' => fn (int $minor): string => self::money($minor, (string) $restaurant->currency),
        ])->render();

        $dompdf = new Dompdf(['isRemoteEnabled' => false, 'defaultFont' => 'DejaVu Sans']);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $this->record($request, $restaurant, $range, 'pdf', count($data['rows']));

        $filename = sprintf('qresto-report-%s-to-%s.pdf', $range['start']->toDateString(), $range['end']->toDateString());

        return response((string) $dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    /**
     * @return array{0: Restaurant, 1: array{start: CarbonImmutable, end: CarbonImmutable, preset: string}, 2: array<string, mixed>}
     */
    private function load(ReportRequest $request): array
    {
        /** @var User $user */
        $user = $request->user();
        $restaurant = $user->restaurant;

        abort_unless($restaurant instanceof Restaurant, 403);

        $range = $this->report->range(
            $restaurant,
            $request->preset(),
            $request->validated('start'),
            $request->validated('end'),
        );

        if ($range['start']->diffInDays($range['end']) + 1 > self::MAX_DAYS) {
            throw ValidationException::withMessages(['end' => 'Pick a range of at most a year.']);
        }

        return [$restaurant, $range, $this->report->build($restaurant, $range['start'], $range['end'])];
    }

    /**
     * @param  array{start: CarbonImmutable, end: CarbonImmutable, preset: string}  $range
     */
    private function record(ReportRequest $request, Restaurant $restaurant, array $range, string $format, int $rows): void
    {
        ReportExport::query()->create([
            'restaurant_id' => $restaurant->id,
            'user_id' => $request->user()?->getKey(),
            'format' => $format,
            'preset' => $range['preset'],
            'range_start' => $range['start']->toDateString(),
            'range_end' => $range['end']->toDateString(),
            'row_count' => $rows,
            'generated_at' => now(),
        ]);
    }

    private static function decimal(int $minor): string
    {
        return number_format($minor / 100, 2, '.', '');
    }

    private static function money(int $minor, string $currency): string
    {
        $formatter = new \NumberFormatter('en', \NumberFormatter::CURRENCY);

        return (string) $formatter->formatCurrency($minor / 100, $currency);
    }
}
