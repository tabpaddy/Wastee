<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

trait HasTemporalOverlap
{
    public function scopeOverlapping(Builder $query, string $from, ?string $to = null): Builder
    {
        $start = $this->qualifyColumn(static::PERIOD_START);
        $end = $this->qualifyColumn(static::PERIOD_END);

        return $query->when($to !== null, fn (Builder $query) => $query->whereDate($start, '<', $to))
            ->where(fn (Builder $query) => $query->whereNull($end)->orWhereDate($end, '>', $from));
    }
}
