<?php

namespace App\Filament\Company\Resources\Residents\Tables;

use App\Filament\Company\Resources\Residents\Schemas\OccupancyForm;
use App\Services\Auth\AuthorizationContext;
use App\Services\Operations\ResidentOccupancyService;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class OccupanciesTable
{
    public static function configure(Table $table): Table
    {
        return $table->modifyQueryUsing(fn ($query) => $query->visibleToCompany(app(AuthorizationContext::class)->companyId())
            ->with('resident', 'property', 'creator', 'endedBy'))
            ->columns([TextColumn::make('resident.full_name')->label('Resident'), TextColumn::make('property.property_code'),
                TextColumn::make('move_in_date')->date(), TextColumn::make('move_out_date')->date(),
                TextColumn::make('occupancy_type')->badge(), TextColumn::make('is_billing_contact')->label('Billing contact at present/departure')
                    ->formatStateUsing(fn ($state) => $state ? 'Yes' : 'No'),
                TextColumn::make('creator.name')->label('Added by'), TextColumn::make('endedBy.name')->label('Ended by'), TextColumn::make('end_reason')->wrap()])
            ->recordActions([self::moveAction(), self::endAction()])->defaultSort('move_in_date', 'desc');
    }

    private static function moveAction(): Action
    {
        return Action::make('moveResident')->visible(fn ($record) => auth()->user()->can('update', $record))
            ->schema(fn ($record) => [OccupancyForm::destination(), ...OccupancyForm::departure($record)])
            ->action(fn ($record, array $data) => app(ResidentOccupancyService::class)->moveResident(auth()->user(), $record,
                $data['destination_uuid'], $data['reason'], $data['replacement_uuid'] ?? null));
    }

    private static function endAction(): Action
    {
        return Action::make('endOccupancy')->color('warning')->requiresConfirmation()
            ->visible(fn ($record) => auth()->user()->can('update', $record))
            ->schema(fn ($record) => OccupancyForm::departure($record))
            ->action(fn ($record, array $data) => app(ResidentOccupancyService::class)->endOccupancy(auth()->user(), $record,
                $data['reason'], $data['replacement_uuid'] ?? null));
    }
}
