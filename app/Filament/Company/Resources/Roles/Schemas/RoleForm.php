<?php

namespace App\Filament\Company\Resources\Roles\Schemas;

use App\Support\PermissionCatalogue;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class RoleForm
{
    public static function configure(Schema $schema): Schema
    {
        $fields = [TextInput::make('name')->required()->minLength(3)->maxLength(100)->columnSpanFull()];
        foreach (PermissionCatalogue::groupedCompanyPermissions() as $domain => $permissions) {
            $options = collect($permissions)->filter(fn ($permission) => auth()->user()->can($permission))
                ->mapWithKeys(fn ($permission) => [$permission => ucfirst(explode('.', $permission)[1]).' '.str_replace('-', ' ', $domain)])->all();
            $fields[] = Section::make(ucwords(str_replace('-', ' ', $domain)))->schema([
                CheckboxList::make('permission_groups.'.$domain)->hiddenLabel()->options($options)->columns(2),
            ]);
        }

        return $schema->components($fields);
    }

    public static function attributes(array $form): array
    {
        return ['name' => $form['name'], 'permissions' => collect($form['permission_groups'] ?? [])->flatten()->values()->all()];
    }

    public static function groups(array $permissions): array
    {
        return collect($permissions)->groupBy(fn ($permission) => explode('.', $permission)[0])->toArray();
    }
}
