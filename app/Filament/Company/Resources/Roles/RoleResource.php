<?php

namespace App\Filament\Company\Resources\Roles;

use App\Filament\Concerns\UsesPublicRecordUrls;
use App\Services\Auth\AuthorizationContext;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Permission\Models\Role;

class RoleResource extends Resource
{
    use UsesPublicRecordUrls;

    protected static ?string $model = Role::class;

    protected static ?string $slug = 'roles';

    protected static ?string $recordRouteKeyName = 'uuid';

    protected static bool $isScopedToTenant = false;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('company_id', app(AuthorizationContext::class)->companyId() ?? -1)
            ->where('guard_name', 'web')->with('permissions');
    }

    public static function form(Schema $schema): Schema
    {
        return Schemas\RoleForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return Schemas\RoleInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return Tables\RolesTable::configure($table);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListRoles::route('/'), 'create' => Pages\CreateRole::route('/create'),
            'view' => Pages\ViewRole::route('/{record}'), 'edit' => Pages\EditRole::route('/{record}/edit')];
    }
}
