<?php

namespace App\Filament\Company\Resources\Properties\Tables;

use App\Services\Auth\AuthorizationContext;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;

class PropertiesTable
{
    public static function configure(Table $table): Table
    {
        return $table->columns([TextColumn::make('property_code')->searchable(), TextColumn::make('building_number'),
            TextColumn::make('street')->searchable(), TextColumn::make('community.name')->searchable(),
            TextColumn::make('property_type')->badge(), TextColumn::make('status')->badge()])
            ->filters([Filter::make('current')->label('Currently served only')->default()
                ->query(fn ($query) => $query->currentlyServedBy(app(AuthorizationContext::class)->companyId()))])
            ->recordActions([ViewAction::make()])->emptyStateDescription('Register a property in active coverage, or assign a known unassigned property by its UUID.');
    }
}
