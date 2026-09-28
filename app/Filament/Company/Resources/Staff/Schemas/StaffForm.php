<?php

namespace App\Filament\Company\Resources\Staff\Schemas;

use App\Services\Staff\CompanyRoleService;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;

class StaffForm
{
    public static function profile(): array
    {
        return [
            TextInput::make('first_name')->required()->maxLength(100),
            TextInput::make('last_name')->required()->maxLength(100),
            TextInput::make('phone')->tel()->maxLength(40)
                ->helperText('Names and phone belong to the shared user account. Login email and password cannot be changed here.'),
        ];
    }

    public static function roles(): Select
    {
        return Select::make('roles')->label('Company roles')->multiple()->required()
            ->options(fn () => app(CompanyRoleService::class)->roleOptions(auth()->user()));
    }
}
