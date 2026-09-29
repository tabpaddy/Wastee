<?php

namespace App\Filament\Company\Resources\ServiceAreas\Schemas;

use App\Models\Community;
use Filament\Forms\Components\Select;

class ServiceAreaForm
{
    public static function fields(): array
    {
        return [Select::make('community_uuid')->label('Community')->required()->searchable()
            ->options(fn () => Community::query()->active()->with('lga.state')->orderBy('name')->get()
                ->mapWithKeys(fn ($community) => [$community->uuid => $community->name.' / '.$community->lga->name.' / '.$community->lga->state->name])->all())
            ->helperText('Coverage starts today. If no communities are available, a platform administrator must configure reference geography.')];
    }
}
