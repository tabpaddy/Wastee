<?php

namespace App\Filament\Company\Resources\Residents\Tables;

use App\Services\Auth\AuthorizationContext;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;

class ResidentsTable
{
    public static function configure(Table $table): Table
    {
        return $table->columns([TextColumn::make('first_name')->searchable(), TextColumn::make('last_name')->searchable(),
            TextColumn::make('phone')->searchable(), TextColumn::make('email')->searchable(), TextColumn::make('status')->badge(),
            TextColumn::make('currentOccupancy.property.property_code')->label('Current property')])
            ->filters([Filter::make('current')->label('Currently served only')->default()
                ->query(fn ($query) => $query->currentlyServedBy(app(AuthorizationContext::class)->companyId()))])
            ->recordActions([ViewAction::make()])->emptyStateDescription('Open a currently serviced property to register or link an occupant.');
    }
}
