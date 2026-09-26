<?php

namespace App\Models;

use App\Enums\CompanyLocationStatus;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyLocation extends Model
{
    use BelongsToCompany, HasPublicUuid;

    protected $fillable = [
        'company_id',
        'label',
        'address_line',
        'community',
        'lga_id',
        'state_id',
        'latitude',
        'longitude',
        'is_head_office',
        'active_from',
        'active_to',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'is_head_office' => 'boolean',
            'active_from' => 'immutable_date',
            'active_to' => 'immutable_date',
            'status' => CompanyLocationStatus::class,
        ];
    }

    public function lga(): BelongsTo
    {
        return $this->belongsTo(Lga::class);
    }

    public function state(): BelongsTo
    {
        return $this->belongsTo(State::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where($this->qualifyColumn('status'), CompanyLocationStatus::Active);
    }

    public function scopeCurrent(Builder $query, ?string $on = null): Builder
    {
        $on ??= today()->toDateString();

        return $query->active()
            ->where(fn (Builder $q) => $q->whereDate('active_from', '<=', $on))
            ->where(fn (Builder $q) => $q->whereNull('active_to')->orWhereDate('active_to', '>', $on));
    }
}
