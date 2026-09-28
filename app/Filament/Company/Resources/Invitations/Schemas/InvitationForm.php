<?php

namespace App\Filament\Company\Resources\Invitations\Schemas;

use App\Services\Staff\CompanyRoleService;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;

class InvitationForm
{
    public static function fields(): array
    {
        return [
            TextInput::make('email')->email()->required()->maxLength(255),
            Select::make('role_uuid')->label('Initial company role')->required()
                ->options(fn () => app(CompanyRoleService::class)->roleOptions(auth()->user(), true)),
        ];
    }
}
