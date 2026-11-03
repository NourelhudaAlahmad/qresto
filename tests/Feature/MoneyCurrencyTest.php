<?php

use App\Models\OrderLine;
use App\Support\Money;
use App\Support\OrderTotals;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Finder\Finder;

uses(RefreshDatabase::class);

/*
 * #49: nothing in the product is priced in Turkish lira. Money names its
 * currency every time, and the one fallback left is config('qresto.currency'),
 * which the database also uses as the default for restaurants and orders.
 */

it('adds up USD lines in USD without a currency clash (#10)', function () {
    $totals = OrderTotals::calculate(
        [
            ['unit_price' => Money::fromDecimal('19.50', 'USD'), 'qty' => 2],
            ['unit_price' => Money::fromDecimal('12.50', 'USD'), 'qty' => 1],
        ],
        servicePct: '12.5',
        tipAmount: Money::fromDecimal('5.00', 'USD'),
    );

    expect($totals->subtotal->currency())->toBe('USD')
        ->and($totals->serviceAmount->currency())->toBe('USD')
        ->and($totals->total->currency())->toBe('USD');
});

it('starts an empty order in the configured currency', function () {
    config(['qresto.currency' => 'EUR']);

    $totals = OrderTotals::calculate([]);

    expect($totals->total->currency())->toBe('EUR')
        ->and($totals->total->amount())->toBe(0);
});

it('casts money on a model with no parent to the configured currency, never TRY', function () {
    config(['qresto.currency' => 'EUR']);

    $line = new OrderLine(['unit_price' => 1234]);

    expect($line->unit_price->currency())->toBe('EUR')
        ->and($line->unit_price->amount())->toBe(1234);
});

it('defaults restaurants and orders to the configured currency in the database', function (string $table) {
    $column = collect(Schema::getColumns($table))->firstWhere('name', 'currency');

    expect($column)->not->toBeNull()
        ->and((string) $column['default'])->toContain(strtoupper((string) config('qresto.currency')));
})->with(['restaurants', 'orders']);

it('keeps TRY out of the app code', function () {
    $found = [];

    foreach (Finder::create()->files()->in(app_path())->name('*.php') as $file) {
        if (str_contains($file->getContents(), "'TRY'")) {
            $found[] = $file->getRelativePathname();
        }
    }

    expect($found)->toBe([]);
});
