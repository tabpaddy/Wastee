<?php

namespace App\Filament\Company\Resources\Properties\RelationManagers;

use App\Services\Auth\AuthorizationContext;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ProviderAssignmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'providerAssignments';

    protected static bool $isLazy = false;

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()->can('view', $ownerRecord);
    }

    public function table(Table $table): Table
    {
        return $table->modifyQueryUsing(fn ($query) => $query->forCompany(app(AuthorizationContext::class)->companyId())
            ->where('assigned_from', '<=', today())->with('creator', 'endedBy'))
            ->columns([TextColumn::make('assigned_from')->date(), TextColumn::make('assigned_to')->date(),
                TextColumn::make('creator.name')->label('Assigned by'), TextColumn::make('reason'),
                TextColumn::make('endedBy.name')->label('Released by'), TextColumn::make('end_reason')->wrap()])
            ->defaultSort('assigned_from', 'desc');
    }
}
