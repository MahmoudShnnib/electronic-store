<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements HasLocalePreference, MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'is_active',
        'locale',
        'last_login_at',
    ];

    // google_id is intentionally NOT fillable — it is only ever written via
    // forceFill in LoginWithGoogle, never from request input.

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    // -- Relationships --------------------------------------------------------

    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function carts(): HasMany
    {
        return $this->hasMany(Cart::class);
    }

    public function wishlistItems(): HasMany
    {
        return $this->hasMany(WishlistItem::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    // -- Helpers --------------------------------------------------------------

    protected static function booted(): void
    {
        // Deactivating an account signs it out of the API everywhere: its bearer
        // tokens would otherwise keep working until someone revoked them.
        static::updated(function (User $user): void {
            if ($user->wasChanged('is_active') && $user->is_active === false) {
                $user->tokens()->delete();
            }
        });

        // A deleted (soft-deleted) account keeps its orders but lets go of its
        // email and Google link, so the person can sign up or sign in again.
        static::deleted(function (User $user): void {
            if ($user->isForceDeleting()) {
                return;
            }

            $user->tokens()->delete();
            $user->forceFill([
                'email' => "deleted-{$user->id}-".Str::lower(Str::random(8)).'@deleted.invalid',
                'google_id' => null,
            ])->saveQuietly();
        });
    }

    /** Emails and notifications go out in the language the customer uses the shop in. */
    public function preferredLocale(): ?string
    {
        return $this->locale;
    }

    public function isStaff(): bool
    {
        // Any role other than "customer" is a staff role — including custom roles
        // built in Admin → Roles. What each can open is still decided by permissions.
        return $this->roles->contains(fn ($role) => $role->name !== 'customer');
    }
}
