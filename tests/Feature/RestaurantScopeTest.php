<?php

use App\Concerns\BelongsToRestaurant;
use App\CurrentRestaurant;
use App\Models\Promotion;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Finder\Finder;

uses(RefreshDatabase::class);

/*
 * #4: every table that carries restaurant_id is scoped by BelongsToRestaurant,
 * so a query that forgets its where-clause cannot read another venue's rows.
 *
 * The only models allowed to skip the trait, each with the reason.
 */
$unscoped = [
    User::class => 'Sign-in resolves a user before any restaurant is known, and super admins belong to none.',
];

$modelsWithRestaurantId = function (): array {
    $models = [];

    foreach (Finder::create()->files()->in(app_path('Models'))->name('*.php') as $file) {
        $class = 'App\\Models\\'.str_replace(['/', '.php'], ['\\', ''], $file->getRelativePathname());

        if (! is_subclass_of($class, Model::class) || (new ReflectionClass($class))->isAbstract()) {
            continue;
        }

        $table = (new $class)->getTable();

        if (Schema::hasTable($table) && Schema::hasColumn($table, 'restaurant_id')) {
            $models[] = $class;
        }
    }

    sort($models);

    return $models;
};

it('scopes every model whose table has restaurant_id', function () use ($unscoped, $modelsWithRestaurantId) {
    $missing = array_values(array_filter(
        $modelsWithRestaurantId(),
        fn (string $class): bool => ! isset($unscoped[$class])
            && ! in_array(BelongsToRestaurant::class, class_uses_recursive($class), true),
    ));

    expect($missing)->toBe(
        [],
        "These models have restaurant_id but no BelongsToRestaurant, so their queries can leak across restaurants:\n  "
            .implode("\n  ", $missing),
    );
});

it('keeps the unscoped list honest', function () use ($unscoped, $modelsWithRestaurantId) {
    $models = $modelsWithRestaurantId();

    foreach (array_keys($unscoped) as $class) {
        expect($models)->toContain($class)
            ->and(class_uses_recursive($class))->not->toContain(BelongsToRestaurant::class);
    }
});

it('hides another restaurant\'s promotions once a restaurant is current', function () {
    $mine = Restaurant::factory()->create();
    $other = Restaurant::factory()->create();

    Promotion::factory()->for($other)->create(['code' => 'OTHER10']);

    app(CurrentRestaurant::class)->set($mine);

    expect(Promotion::query()->where('code', 'OTHER10')->exists())->toBeFalse();

    app(CurrentRestaurant::class)->clear();

    expect(Promotion::query()->where('code', 'OTHER10')->exists())->toBeTrue();
});

it('fills restaurant_id from the current restaurant on create', function () {
    $restaurant = Restaurant::factory()->create();

    app(CurrentRestaurant::class)->set($restaurant);

    $promotion = Promotion::factory()->create(['restaurant_id' => null]);

    expect($promotion->restaurant_id)->toBe($restaurant->id);
});
