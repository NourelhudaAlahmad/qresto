<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\Restaurant;
use App\Models\User;
use App\Notifications\StaffInvitation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Throwable;

/**
 * The first restaurant and its admin on a fresh install (#59). Seeding
 * production creates only the roles; without this the only way in was the
 * demo seeder or tinker.
 *
 * The admin is invited by email, never given a password on the console,
 * so this is also the first proof that mail is configured.
 */
class CreateRestaurant extends Command
{
    protected $signature = 'qresto:create-restaurant
        {name : The restaurant name guests see}
        {--admin-name= : The admin\'s full name}
        {--admin-email= : Where the admin\'s invitation goes}
        {--timezone=UTC : The restaurant\'s timezone, e.g. Europe/Istanbul}
        {--currency= : ISO currency code (defaults to QRESTO_CURRENCY)}';

    protected $description = 'Create a restaurant and invite its first admin by email';

    public function handle(): int
    {
        $validator = Validator::make([
            'name' => $this->argument('name'),
            'admin_name' => $this->option('admin-name'),
            'admin_email' => $this->option('admin-email'),
            'timezone' => $this->option('timezone'),
            'currency' => $this->option('currency') ?: config('qresto.currency'),
        ], [
            'name' => ['required', 'string', 'max:120'],
            'admin_name' => ['required', 'string', 'max:255'],
            'admin_email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'timezone' => ['required', 'string', 'timezone:all'],
            'currency' => ['required', 'string', 'size:3'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $valid = $validator->safe();

        $input = [
            'name' => $valid->string('name')->toString(),
            'admin_name' => $valid->string('admin_name')->toString(),
            'admin_email' => $valid->string('admin_email')->toString(),
            'timezone' => $valid->string('timezone')->toString(),
            'currency' => $valid->string('currency')->upper()->toString(),
        ];

        [$restaurant, $admin] = DB::transaction(function () use ($input): array {
            $restaurant = Restaurant::query()->create([
                'name' => $input['name'],
                'slug' => $this->uniqueSlug($input['name']),
                'currency' => $input['currency'],
                'timezone' => $input['timezone'],
                'default_locale' => 'en',
                'supported_locales' => array_keys((array) config('qresto.locales')),
            ]);

            $admin = User::query()->create([
                'name' => $input['admin_name'],
                'email' => $input['admin_email'],
                // Never used: the admin sets their own from the invitation.
                'password' => Str::password(40),
                'restaurant_id' => $restaurant->id,
                'is_active' => true,
                'initials' => $this->initials($input['admin_name']),
            ]);

            $admin->assignRole(Role::ADMIN->value);

            return [$restaurant, $admin];
        });

        $this->info("Created {$restaurant->name} (#{$restaurant->id}) with admin {$admin->email}.");

        try {
            // Sent now, not queued: a mail problem shows up here, at install.
            $admin->notifyNow(new StaffInvitation(
                token: Password::broker('staff_invites')->createToken($admin),
                expiresInMinutes: (int) config('auth.passwords.staff_invites.expire'),
                invitedBy: 'QResto setup',
            ));
        } catch (Throwable $exception) {
            $this->error("The invitation could not be sent: {$exception->getMessage()}");
            $this->line('Fix MAIL_* in .env, then use "Forgot password" on the staff sign-in page with that email.');

            return self::FAILURE;
        }

        $this->info("Invitation sent to {$admin->email}. The link sets their password and signs them in.");

        return self::SUCCESS;
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'restaurant';
        $slug = $base;
        $suffix = 2;

        while (Restaurant::query()->withoutGlobalScopes()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    private function initials(string $name): string
    {
        return Str::upper(
            collect(preg_split('/\s+/', trim($name)) ?: [])
                ->filter()
                ->take(2)
                ->map(fn (string $part): string => Str::substr($part, 0, 1))
                ->implode(''),
        );
    }
}
