<?php

use Database\Seeders\AlBustanSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([RolesSeeder::class, AlBustanSeeder::class]);
});

it('gives the landing page the design copy, including the hero intro', function () {
    $this->get('/')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('guest/landing')
            ->where('restaurant.name', 'Al Bustan')
            ->where('restaurant.tagline', 'Charcoal, citrus, and a long table.')
            ->where('restaurant.cuisine', 'Modern Levantine')
            ->where('restaurant.intro', 'Al Bustan cooks over live fire, seven nights a week.')
            ->where('translations.until', 'Until :time'));
});

it('serves the restaurant copy in Arabic', function () {
    $this->withSession(['locale' => 'ar'])
        ->get('/')
        ->assertInertia(fn ($page) => $page
            ->where('restaurant.name', 'البستان')
            ->where('restaurant.intro', 'البستان يطبخ على نار حيّة، سبع ليالٍ في الأسبوع.'));
});
