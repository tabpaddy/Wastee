<?php

namespace App\Filament\Platform\Resources\Companies\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

class ApprovalLogsRelationManager extends RelationManager
{
    protected static string $relationship = 'approvalLogs';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return Gate::allows('platform.companies.view');
    }

    public function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('action')->formatStateUsing(fn ($state) => str_replace('_', ' ', $state->value)),
            TextColumn::make('from_status')->formatStateUsing(fn ($state) => str_replace('_', ' ', $state->value)),
            TextColumn::make('to_status')->formatStateUsing(fn ($state) => str_replace('_', ' ', $state->value)),
            TextColumn::make('actor.name'),
            TextColumn::make('remarks')->wrap(),
            TextColumn::make('created_at')->dateTime(),
        ])->defaultSort('id', 'desc');
    }
}
