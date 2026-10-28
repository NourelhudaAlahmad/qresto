<?php

use App\Models\Restaurant;
use App\Models\ServiceRequest;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesSeeder::class);
});

test('manager can access the staff overview', function () {
    $manager = User::factory()->create([
        'restaurant_id' => Restaurant::factory()->create()->id,
    ]);
    $manager->assignRole('manager');

    $this->actingAs($manager)
        ->get('/manager/overview')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('staff/manager/overview'));

    // The old address forwards to it.
    $this->actingAs($manager)
        ->get('/app/overview')
        ->assertRedirect('/manager/overview');
});

test('staff without the capability cannot open the overview', function () {
    foreach (['waiter', 'kitchen'] as $role) {
        $user = User::factory()->create([
            'restaurant_id' => Restaurant::factory()->create()->id,
        ]);
        $user->assignRole($role);

        $this->actingAs($user)
            ->get('/manager/overview')
            ->assertForbidden();
    }
});

test('the kitchen cannot acknowledge guest requests', function () {
    $request = ServiceRequest::factory()->pending()->create();

    $kitchen = User::factory()->create([
        'restaurant_id' => $request->table->restaurant_id,
    ]);
    $kitchen->assignRole('kitchen');

    $this->actingAs($kitchen)
        ->patch("/service-requests/{$request->id}/acknowledge")
        ->assertForbidden();

    expect($request->fresh()->status->value ?? $request->fresh()->status)->toBe('pending');
});
