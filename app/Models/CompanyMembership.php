<?php

namespace App\Models;

use App\Enums\CompanyMembershipStatus;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CompanyMembership extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'user_id',
        'status',
        'joined_at',
        'left_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => CompanyMembershipStatus::class,
            'joined_at' => 'immutable_datetime',
            'left_at' => 'immutable_datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function periods(): HasMany
    {
        return $this->hasMany(CompanyMembershipPeriod::class);
    }

    public function getUserUuidAttribute(): string
    {
        return $this->user->uuid;
    }

    public function scopeForStaffList(Builder $query, int $companyId): Builder
    {
        return $query->where('company_id', $companyId)->with(['user.roles' => fn ($roles) => $roles->where('roles.company_id', $companyId)->where('model_has_roles.company_id', $companyId)]);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where($this->qualifyColumn('status'), CompanyMembershipStatus::Active);
    }

    public function scopeCurrent(Builder $query): Builder
    {
        return $query->active()->where('joined_at', '<=', now())
            ->where(fn (Builder $query) => $query->whereNull('left_at')->orWhere('left_at', '>', now()));
    }
}
