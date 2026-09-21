<?php

use App\Support\LiveResponse;
use Illuminate\Database\Eloquent\Collection;

it('returns the live collection and a version', function (): void {
    $items = new Collection([
        (object) [
            'id' => 1,
            'updated_at' => now()->subMinutes(2),
        ],
        (object) [
            'id' => 2,
            'updated_at' => now()->subMinute(),
        ],
    ]);

    $response = LiveResponse::make($items);

    expect($response)
        ->toHaveKeys(['items', 'version'])
        ->and($response['items'])->toBe($items)
        ->and($response['version'])->toContain(':2');
});

it('uses none when the collection has no updated timestamp', function (): void {
    $items = new Collection([
        (object) ['id' => 1],
    ]);

    $response = LiveResponse::make($items);

    expect($response['version'])->toBe('none:1');
});

it('changes the version when the collection count changes', function (): void {
    $timestamp = now();

    $first = new Collection([
        (object) [
            'id' => 1,
            'updated_at' => $timestamp,
        ],
    ]);

    $second = new Collection([
        (object) [
            'id' => 1,
            'updated_at' => $timestamp,
        ],
        (object) [
            'id' => 2,
            'updated_at' => $timestamp,
        ],
    ]);

    expect(LiveResponse::make($first)['version'])
        ->not->toBe(LiveResponse::make($second)['version']);
});

it('detects when the live version has not changed', function (): void {
    $timestamp = now();

    $items = new Collection([
        (object) [
            'id' => 1,
            'updated_at' => $timestamp,
        ],
    ]);

    $version = LiveResponse::version($items);

    expect(LiveResponse::unchanged($items, $version))
        ->toBeTrue()
        ->and(LiveResponse::unchanged($items, 'different-version'))
        ->toBeFalse()
        ->and(LiveResponse::unchanged($items, null))
        ->toBeFalse();
});
it('changes the version when an item is updated', function (): void {
    $timestamp = now();

    $first = new Collection([
        (object) [
            'id' => 1,
            'updated_at' => $timestamp,
        ],
    ]);

    $second = new Collection([
        (object) [
            'id' => 1,
            'updated_at' => $timestamp->copy()->addSecond(),
        ],
    ]);

    expect(LiveResponse::make($first)['version'])
        ->not->toBe(LiveResponse::make($second)['version']);
});
