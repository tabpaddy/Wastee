<?php

namespace App\Filament\Company\Resources\Staff\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class MembershipPeriodsRelationManager extends RelationManager
{
    protected static string $relationship = 'periods';

    protected static bool $isLazy = false;

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()->can('view', $ownerRecord);
    }

    public function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('joined_at')->dateTime(), TextColumn::make('left_at')->dateTime(),
            TextColumn::make('joinedBy.name')->label('Joined by'), TextColumn::make('leftBy.name')->label('Ended by'),
            TextColumn::make('join_reason')->wrap(), TextColumn::make('leave_reason')->wrap(),
            TextColumn::make('joined_roles')->badge(), TextColumn::make('left_roles')->badge(),
        ])->defaultSort('joined_at', 'desc');
    }
}
