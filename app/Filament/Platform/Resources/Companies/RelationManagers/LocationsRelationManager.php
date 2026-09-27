<?php

namespace App\Filament\Platform\Resources\Companies\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

class LocationsRelationManager extends RelationManager
{
    protected static string $relationship = 'locations';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return Gate::allows('platform.companies.view');
    }

    public function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('label'),
            TextColumn::make('address_line'),
            TextColumn::make('community'),
            TextColumn::make('lga.name'),
            TextColumn::make('state.name'),
            TextColumn::make('active_from')->date(),
            TextColumn::make('active_to')->date(),
        ])->defaultSort('id', 'desc');
    }
}
