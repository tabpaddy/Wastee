<?php

namespace App\Filament\Company\Resources\Properties\RelationManagers;

use App\Filament\Company\Resources\Residents\Tables\OccupanciesTable;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class OccupanciesRelationManager extends RelationManager
{
    protected static string $relationship = 'occupancies';

    protected static bool $isLazy = false;

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()->can('residents.view') && auth()->user()->can('view', $ownerRecord);
    }

    public function table(Table $table): Table
    {
        return OccupanciesTable::configure($table);
    }
}
