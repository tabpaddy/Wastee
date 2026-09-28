<?php

namespace App\Filament\Company\Resources\Roles\Tables;

use App\Support\PermissionCatalogue;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RolesTable
{
    public static function configure(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->searchable(),
            TextColumn::make('definition')->state(fn ($record) => array_key_exists($record->name, PermissionCatalogue::companyRoles()) ? 'System default' : 'Custom'),
            TextColumn::make('permissions_count')->counts('permissions')->label('Permissions'),
        ])->recordActions([ViewAction::make(), EditAction::make()]);
    }
}
