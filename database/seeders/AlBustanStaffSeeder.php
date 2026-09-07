<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Database\Seeder;

class AlBustanStaffSeeder extends Seeder
{
    public function run(): void
    {
        $restaurant = Restaurant::where('slug', 'al-bustan')->firstOrFail();

        $staff = [
            [
                'name' => 'Nadia Rahman',
                'email' => 'nadia@albustan.co',
                'initials' => 'NR',
                'job_title' => 'Waiter',
                'role' => Role::WAITER,
            ],
            [
                'name' => 'Omar Faruk',
                'email' => 'omar.faruk@al-bustan.test',
                'initials' => 'OF',
                'job_title' => 'Waiter',
                'role' => Role::WAITER,
            ],
            [
                'name' => 'Lin Wu',
                'email' => 'lin.wu@al-bustan.test',
                'initials' => 'LW',
                'job_title' => 'Waiter',
                'role' => Role::WAITER,
            ],
            [
                'name' => 'Sara Malik',
                'email' => 'sara.malik@al-bustan.test',
                'initials' => 'SM',
                'job_title' => 'Manager',
                'role' => Role::MANAGER,
            ],
            [
                'name' => 'Yusuf Aziz',
                'email' => 'yusuf.aziz@al-bustan.test',
                'initials' => 'YA',
                'job_title' => 'Admin',
                'role' => Role::ADMIN,
            ],
            [
                'name' => 'Kitchen Pass',
                'email' => 'kitchen@al-bustan.test',
                'initials' => 'KP',
                'job_title' => 'Kitchen',
                'role' => Role::KITCHEN,
            ],
        ];

        foreach ($staff as $member) {
            $role = $member['role'];

            unset($member['role']);

            $user = User::updateOrCreate(
                [
                    'email' => $member['email'],
                ],
                [
                    ...$member,
                    'restaurant_id' => $restaurant->id,
                    'password' => 'password',
                    'is_active' => true,
                    'email_verified_at' => now(),
                ],
            );

            $user->syncRoles([$role->value]);
        }
    }
}
