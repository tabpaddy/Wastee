<?php

namespace App\Filament\Company\Resources\Roles\Schemas;

use App\Support\PermissionCatalogue;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class RoleInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('name'),
            TextEntry::make('definition')->state(fn ($record) => array_key_exists($record->name, PermissionCatalogue::companyRoles())
                ? 'System default: protected from company edits. Create a custom role to choose different permissions.' : 'Custom company role'),
            TextEntry::make('permissions.name')->listWithLineBreaks(),
        ]);
    }
}
