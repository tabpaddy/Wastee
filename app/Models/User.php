<?php

namespace App\Models;

use App\Enums\UserStatus;
use App\Models\Concerns\HasPublicUuid;
use App\Services\Auth\PanelAccess;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser, HasTenants, MustVerifyEmail
{
    use HasFactory, HasPublicUuid, HasRoles, Notifiable;

    protected $fillable = ['name', 'first_name', 'last_name', 'email', 'phone', 'password'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'status' => UserStatus::class,
            'email_verified_at' => 'immutable_datetime',
            'last_login_at' => 'immutable_datetime',
            'password' => 'hashed',
        ];
    }

    public function ownedCompanies(): HasMany
    {
        return $this->hasMany(Company::class, 'owner_user_id');
    }

    public function companyMemberships(): HasMany
    {
        return $this->hasMany(CompanyMembership::class);
    }

    public function resident(): HasOne
    {
        return $this->hasOne(Resident::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', UserStatus::Active);
    }

    public function canAccessPanel(Panel $panel): bool
    {
        $access = app(PanelAccess::class);

        return match ($panel->getId()) {
            'platform' => $access->platform($this),
            'company' => $access->companies($this)->isNotEmpty(),
            default => false,
        };
    }

    public function getTenants(Panel $panel): Collection
    {
        return $panel->getId() === 'company' ? app(PanelAccess::class)->companies($this) : collect();
    }

    public function canAccessTenant(Model $tenant): bool
    {
        return $tenant instanceof Company && app(PanelAccess::class)->company($this, $tenant);
    }
}
