<?php

namespace App\Filament\Platform\Resources\Communities\Tables;

use App\Filament\Platform\Resources\Communities\Schemas\CommunityForm;
use App\Services\Operations\CommunityService;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CommunitiesTable
{
    public static function configure(Table $table): Table
    {
        return $table->columns([TextColumn::make('name')->searchable(), TextColumn::make('lga.name')->label('LGA'),
            TextColumn::make('lga.state.name')->label('State'), TextColumn::make('ward'), TextColumn::make('status')->badge()])
            ->recordActions([Action::make('editCommunity')->visible(fn ($record) => auth()->user()->can('update', $record))
                ->schema(CommunityForm::fields(false))->fillForm(fn ($record) => $record->only('name', 'ward', 'postal_code'))
                ->action(fn ($record, array $data) => app(CommunityService::class)->update(auth()->user(), $record, $data))])
            ->emptyStateDescription('Configure communities using existing verified State/LGA reference data. No nationwide dataset is bundled.');
    }
}
