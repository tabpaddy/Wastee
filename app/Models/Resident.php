<?php

namespace App\Models;

use App\Enums\ResidentStatus;
use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Resident extends Model
{
    use HasFactory, HasPublicUuid;

    protected $fillable = [
        'user_id',
        'first_name',
        'last_name',
        'phone',
        'email',
        'gender',
        'date_of_birth',
        'occupation',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'immutable_date',
            'status' => ResidentStatus::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function occupancies(): HasMany
    {
        return $this->hasMany(PropertyOccupancy::class);
    }

    public function currentOccupancy(): HasOne
    {
        return $this->hasOne(PropertyOccupancy::class)->current();
    }

    public function complaints(): HasMany
    {
        return $this->hasMany(Complaint::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where($this->qualifyColumn('status'), ResidentStatus::Active);
    }
}
