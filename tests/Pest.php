<?php

use App\Models\RestaurantTable;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * Give a staff member these tables on a live shift tonight, the way the
 * manager's drawer does (#27). A waiter may only act on their own tables.
 */
function assignTonight(User $user, RestaurantTable ...$tables): Shift
{
    $shift = Shift::query()->create([
        'restaurant_id' => $user->restaurant_id,
        'user_id' => $user->id,
        'starts_at' => now()->subHour(),
        'ends_at' => now()->addHours(6),
    ]);

    foreach ($tables as $table) {
        DB::table('table_user')->insert([
            'shift_id' => $shift->id,
            'user_id' => $user->id,
            'table_id' => $table->id,
        ]);
    }

    return $shift;
}
