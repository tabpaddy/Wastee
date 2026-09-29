<?php

namespace App\Filament\Company\Resources\ServiceAreas\Tables;

use App\Services\Operations\CompanyServiceAreaService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;

class ServiceAreasTable
{
    public static function configure(Table $table): Table
    {
        return $table->columns([TextColumn::make('community.name')->searchable(), TextColumn::make('community.lga.name')->label('LGA'),
            TextColumn::make('active_from')->date(), TextColumn::make('active_to')->date(),
            TextColumn::make('status')->badge(), TextColumn::make('creator.name')->label('Added by'),
            TextColumn::make('endedBy.name')->label('Ended by'), TextColumn::make('end_reason')->wrap()])
            ->filters([Filter::make('current')->label('Current coverage only')->default()->query(fn ($query) => $query->current())])
            ->recordActions([Action::make('closeCoverage')->color('danger')->requiresConfirmation()
                ->modalDescription('Ends coverage today and releases every affected property. Periods starting today or in the future must be resolved before closure.')
                ->visible(fn ($record) => auth()->user()->can('update', $record) && (! $record->active_to || $record->active_to->isFuture()))
                ->schema([Textarea::make('reason')->required()->minLength(5)->maxLength(2000)])
                ->action(fn ($record, array $data) => app(CompanyServiceAreaService::class)->close(auth()->user(), $record, $data['reason']))])
            ->emptyStateHeading('No service coverage')->emptyStateDescription('Add an existing community. Platform administrators configure missing reference geography.')
            ->defaultSort('active_from', 'desc');
    }
}
