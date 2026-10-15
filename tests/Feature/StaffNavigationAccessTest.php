<?php

use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesSeeder::class);
});

test('manager can access staff overview', function () {
    $manager = User::factory()->create();
    $manager->assignRole('manager');

    $this->actingAs($manager)
        ->get('/app/overview')
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('staff/landing')
                ->where('title', 'Overview')
        );
});

test('staff without permission cannot access overview', function () {
    $waiter = User::factory()->create();
    $waiter->assignRole('waiter');

    $this->actingAs($waiter)
        ->get('/app/overview')
        ->assertForbidden();
});
