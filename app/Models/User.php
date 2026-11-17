<?php

namespace App\Models;

use App\Enums\Role;
use App\Services\RestaurantCapabilities;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Contracts\Permission;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property int|null $restaurant_id
 * @property string|null $locale
 * @property bool $is_active
 * @property Carbon|null $deactivated_at
 * @property int|null $deactivated_by
 * @property string|null $initials
 * @property string|null $job_title
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'name',
    'email',
    'password',
    'restaurant_id',
    'locale',
    'is_active',
    'deactivated_at',
    'deactivated_by',
    'initials',
    'job_title',
    'failed_attempts',
    'locked_until',
    'last_login_at',
])]
#[Hidden([
    'password',
    'two_factor_secret',
    'two_factor_recovery_codes',
    'remember_token',
])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * @return BelongsTo<Restaurant, $this>
     */
    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    /**
     * @return HasMany<Shift, $this>
     */
    public function shifts(): HasMany
    {
        return $this->hasMany(Shift::class);
    }

    public function isDeactivated(): bool
    {
        return $this->deactivated_at !== null;
    }

    public function canSignInWithShiftQr(): bool
    {
        return ! $this->hasAnyRole(Role::excludedFromShiftQr());
    }

    /**
     * A role's capabilities follow the user's restaurant: its admin may
     * have changed the default grid for that restaurant alone.
     */
    protected function hasPermissionViaRole(Permission $permission): bool
    {
        return in_array(
            $permission->getAttribute('name'),
            app(RestaurantCapabilities::class)->forUser($this),
            true,
        );
    }

    /**
     * @return Collection<int, Model>
     */
    public function getPermissionsViaRoles(): Collection
    {
        $names = app(RestaurantCapabilities::class)->forUser($this);

        return app(PermissionRegistrar::class)
            ->getPermissions(['guard_name' => 'web'])
            ->filter(fn (Model $permission): bool => in_array($permission->getAttribute('name'), $names, true))
            ->sortBy('name')
            ->values();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'is_active' => 'boolean',
            'deactivated_at' => 'datetime',
            'failed_attempts' => 'integer',
            'locked_until' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }
}
