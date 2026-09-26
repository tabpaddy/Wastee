<?php

namespace App\Models;

use App\Enums\CommunityStatus;
use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Community extends Model
{
    use HasFactory, HasPublicUuid;

    protected $fillable = [
        'lga_id',
        'name',
        'ward',
        'postal_code',
        'latitude',
        'longitude',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'status' => CommunityStatus::class,
        ];
    }

    public function lga(): BelongsTo
    {
        return $this->belongsTo(Lga::class);
    }

    public function properties(): HasMany
    {
        return $this->hasMany(Property::class);
    }

    public function companyServiceAreas(): HasMany
    {
        return $this->hasMany(CompanyServiceArea::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where($this->qualifyColumn('status'), CommunityStatus::Active);
    }
}
