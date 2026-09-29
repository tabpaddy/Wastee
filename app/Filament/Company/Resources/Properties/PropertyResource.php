<?php

namespace App\Filament\Company\Resources\Properties;

use App\Filament\Concerns\UsesPublicRecordUrls;
use App\Models\Property;
use App\Services\Auth\AuthorizationContext;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PropertyResource extends Resource
{
    use UsesPublicRecordUrls;

    protected static ?string $model = Property::class;

    protected static ?string $recordRouteKeyName = 'uuid';

    protected static bool $isScopedToTenant = false;

    protected static string|\UnitEnum|null $navigationGroup = 'Operations';

    public static function getEloquentQuery(): Builder
    {
        $companyId = app(AuthorizationContext::class)->companyId() ?? -1;

        return parent::getEloquentQuery()->visibleToCompany($companyId)->with(['community.lga.state',
            'currentProviderAssignment' => fn ($query) => $query->forCompany($companyId)->with('company')]);
    }

    public static function form(Schema $schema): Schema
    {
        return Schemas\PropertyForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return Schemas\PropertyInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return Tables\PropertiesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [RelationManagers\ProviderAssignmentsRelationManager::class, RelationManagers\OccupanciesRelationManager::class];
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListProperties::route('/'), 'create' => Pages\CreateProperty::route('/create'), 'view' => Pages\ViewProperty::route('/{record}')];
    }
}
