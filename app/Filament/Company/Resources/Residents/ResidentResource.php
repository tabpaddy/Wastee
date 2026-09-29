<?php

namespace App\Filament\Company\Resources\Residents;

use App\Filament\Concerns\UsesPublicRecordUrls;
use App\Models\Resident;
use App\Services\Auth\AuthorizationContext;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ResidentResource extends Resource
{
    use UsesPublicRecordUrls;

    protected static ?string $model = Resident::class;

    protected static ?string $recordRouteKeyName = 'uuid';

    protected static bool $isScopedToTenant = false;

    protected static string|\UnitEnum|null $navigationGroup = 'Operations';

    public static function getEloquentQuery(): Builder
    {
        $companyId = app(AuthorizationContext::class)->companyId() ?? -1;

        return parent::getEloquentQuery()->visibleToCompany($companyId)->with([
            'currentOccupancy' => fn ($query) => $query->whereHas('property', fn ($properties) => $properties->currentlyServedBy($companyId))
                ->with(['property.currentProviderAssignment' => fn ($assignments) => $assignments->forCompany($companyId)->with('company')])]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function infolist(Schema $schema): Schema
    {
        return Schemas\ResidentInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return Tables\ResidentsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [RelationManagers\OccupanciesRelationManager::class];
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListResidents::route('/'), 'view' => Pages\ViewResident::route('/{record}')];
    }
}
